<?php
/**
 * Real-WordPress contract test: head-to-head match votes cannot be stuffed.
 *
 * Regression for `POST /peanut-festival/v1/matches/{id}/vote`: the endpoint is
 * public, had no rate limit or origin check, deduplicated on a client-supplied
 * `voter_id` (so rotating it gave unlimited votes), stored the "already voted"
 * marker in a non-atomic transient, and incremented counts read-modify-write.
 */

namespace Peanut_Festival\Tests\ContractWp;

use WP_UnitTestCase;
use WP_REST_Request;

class MatchVoteIntegrityTest extends WP_UnitTestCase {

    private int $match_id = 0;

    public function set_up(): void {
        parent::set_up();

        \Peanut_Festival_Activator::activate();
        \Peanut_Festival_Migrations::run();

        global $wpdb;
        $suppress = $wpdb->suppress_errors(true);
        $wpdb->query("DELETE FROM {$wpdb->prefix}pf_competition_matches");
        $wpdb->query("DELETE FROM {$wpdb->prefix}pf_match_votes");
        $wpdb->suppress_errors($suppress);

        $wpdb->insert("{$wpdb->prefix}pf_competition_matches", [
            'competition_id' => 1,
            'round_number' => 1,
            'match_number' => 1,
            'performer_1_id' => 101,
            'performer_2_id' => 102,
            'votes_performer_1' => 0,
            'votes_performer_2' => 0,
            'status' => 'voting',
            'voting_opens_at' => gmdate('Y-m-d H:i:s', time() - 60),
            'voting_closes_at' => gmdate('Y-m-d H:i:s', time() + 3600),
        ]);
        $this->match_id = (int) $wpdb->insert_id;

        $_SERVER['REMOTE_ADDR'] = '198.51.100.10';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Contract Test)';
        unset($_COOKIE['pf_voter']);

        global $wp_rest_server;
        $wp_rest_server = null;
        do_action('rest_api_init');
    }

    public function tear_down(): void {
        unset($_COOKIE['pf_voter']);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        parent::tear_down();
    }

    /**
     * @param array<string,mixed> $opts
     */
    private function vote(int $performer_id, array $opts = []): \WP_REST_Response {
        if (array_key_exists('ip', $opts)) {
            $_SERVER['REMOTE_ADDR'] = $opts['ip'];
        }
        if (array_key_exists('ua', $opts)) {
            $_SERVER['HTTP_USER_AGENT'] = $opts['ua'];
        }
        if (array_key_exists('cookie', $opts)) {
            if ($opts['cookie'] === null) {
                unset($_COOKIE['pf_voter']);
            } else {
                $_COOKIE['pf_voter'] = $opts['cookie'];
            }
        }

        $request = new WP_REST_Request('POST', '/peanut-festival/v1/matches/' . $this->match_id . '/vote');
        $request->set_param('performer_id', $performer_id);
        if (isset($opts['voter_id'])) {
            $request->set_param('voter_id', $opts['voter_id']);
        }

        $origin = array_key_exists('origin', $opts) ? $opts['origin'] : home_url();
        if ($origin !== null) {
            $request->set_header('Origin', $origin);
        }
        if (isset($opts['referer'])) {
            $request->set_header('Referer', $opts['referer']);
        }

        return rest_get_server()->dispatch($request);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function counts(): array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT votes_performer_1, votes_performer_2 FROM {$wpdb->prefix}pf_competition_matches WHERE id = %d",
            $this->match_id
        ));
        return [(int) $row->votes_performer_1, (int) $row->votes_performer_2];
    }

    public function test_rotating_client_supplied_voter_id_does_not_grant_extra_votes(): void {
        $this->assertSame(200, $this->vote(101, ['voter_id' => 'v-0'])->get_status());

        for ($i = 1; $i <= 5; $i++) {
            $response = $this->vote(101, ['voter_id' => 'v-' . $i]);
            $this->assertSame(400, $response->get_status(), wp_json_encode($response->get_data()));
        }

        $this->assertSame([1, 0], $this->counts());
    }

    public function test_signed_voter_cookie_blocks_a_revote_from_another_network(): void {
        $cookie = \Peanut_Festival_Voter_Identity::cookie_value_for(str_repeat('a', 32));

        $this->assertSame(200, $this->vote(101, ['cookie' => $cookie, 'ip' => '198.51.100.20'])->get_status());
        $this->assertSame(400, $this->vote(102, ['cookie' => $cookie, 'ip' => '203.0.113.20'])->get_status());

        $this->assertSame([1, 0], $this->counts());
    }

    public function test_forged_voter_cookie_is_ignored(): void {
        $forged = str_repeat('b', 32) . '.' . str_repeat('0', 64);

        $this->assertNull(\Peanut_Festival_Voter_Identity::read_cookie_id($forged));
        $this->assertSame(
            str_repeat('b', 32),
            \Peanut_Festival_Voter_Identity::read_cookie_id(\Peanut_Festival_Voter_Identity::cookie_value_for(str_repeat('b', 32)))
        );
    }

    public function test_distinct_voters_are_all_counted(): void {
        $this->assertSame(200, $this->vote(101, ['ip' => '198.51.100.31'])->get_status());
        $this->assertSame(200, $this->vote(101, ['ip' => '198.51.100.32'])->get_status());
        $this->assertSame(200, $this->vote(102, ['ip' => '198.51.100.33'])->get_status());

        $this->assertSame([2, 1], $this->counts());
    }

    public function test_cross_origin_vote_is_rejected(): void {
        $response = $this->vote(101, ['origin' => 'https://evil.example']);

        $this->assertSame(403, $response->get_status());
        $this->assertSame([0, 0], $this->counts());
    }

    public function test_vote_without_origin_or_referer_is_rejected(): void {
        $response = $this->vote(101, ['origin' => null]);

        $this->assertSame(403, $response->get_status());
        $this->assertSame([0, 0], $this->counts());
    }

    public function test_same_site_referer_is_accepted_without_origin(): void {
        $response = $this->vote(101, ['origin' => null, 'referer' => home_url('/vote/')]);

        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
    }

    public function test_votes_are_rate_limited_per_client_ip(): void {
        $limit = \Peanut_Festival_Rate_Limiter::get_limit('match_vote');
        $this->assertGreaterThan(0, $limit);

        // A new User-Agent per request is a new voter, but the IP's budget is shared.
        for ($i = 0; $i < $limit; $i++) {
            $this->assertSame(200, $this->vote(101, ['ua' => 'Agent/' . $i])->get_status());
        }

        $this->assertSame(429, $this->vote(101, ['ua' => 'Agent/overflow'])->get_status());
        $this->assertSame([$limit, 0], $this->counts());
    }

    public function test_closed_match_rejects_votes(): void {
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}pf_competition_matches", ['status' => 'completed'], ['id' => $this->match_id]);

        $this->assertSame(400, $this->vote(101)->get_status());
        $this->assertSame([0, 0], $this->counts());
        $this->assertSame(0, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pf_match_votes"));
    }

    public function test_database_rejects_a_second_ballot_for_the_same_voter(): void {
        global $wpdb;
        $row = [
            'match_id' => $this->match_id,
            'performer_id' => 101,
            'voter_hash' => str_repeat('c', 64),
            'client_hash' => str_repeat('d', 64),
        ];
        $this->assertSame(1, $wpdb->insert("{$wpdb->prefix}pf_match_votes", $row));

        $suppress = $wpdb->suppress_errors(true);
        $row['client_hash'] = str_repeat('e', 64);
        $second = $wpdb->insert("{$wpdb->prefix}pf_match_votes", $row);
        $wpdb->suppress_errors($suppress);

        $this->assertFalse($second, '(match_id, voter_hash) must be UNIQUE.');
    }
}
