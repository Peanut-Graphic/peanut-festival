<?php
/**
 * Real-WordPress contract test: a show-vote ballot is one ranked list of
 * distinct performers from the active group, at most three long, recorded at
 * most once per voter.
 *
 * Regression for `POST /peanut-festival/v1/vote/submit`, which recorded
 * whatever `performer_ids` it was given: repeats of one performer (each rank
 * adding weighted points), performers outside the active group, and any number
 * of entries. The "already voted" check was also check-then-insert, with no
 * database constraint behind it.
 */

namespace Peanut_Festival\Tests\ContractWp;

use WP_UnitTestCase;
use WP_REST_Request;

class ShowVoteBallotTest extends WP_UnitTestCase {

    private const SHOW = 'contract-show';

    public function set_up(): void {
        parent::set_up();

        \Peanut_Festival_Activator::activate();
        \Peanut_Festival_Migrations::run();

        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}pf_votes");

        \Peanut_Festival_Voting::save_show_config(self::SHOW, [
            'groups' => [
                'group_1' => [11, 12, 13, 14],
                'group_2' => [21, 22],
            ],
            'active_group' => 'group_1',
            'timer_duration' => 0,
        ]);

        $_SERVER['REMOTE_ADDR'] = '198.51.100.' . wp_rand(1, 250);
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Contract Test)';

        global $wp_rest_server;
        $wp_rest_server = null;
        do_action('rest_api_init');
    }

    public function tear_down(): void {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        parent::tear_down();
    }

    /**
     * @param mixed $performer_ids
     */
    private function submit($performer_ids, string $token = 'tok-1'): \WP_REST_Response {
        $request = new WP_REST_Request('POST', '/peanut-festival/v1/vote/submit');
        $request->set_param('show_slug', self::SHOW);
        $request->set_param('performer_ids', $performer_ids);
        $request->set_param('token', $token);
        return rest_get_server()->dispatch($request);
    }

    /**
     * @return array<int,array{performer_id:int,vote_rank:int}>
     */
    private function rows(): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT performer_id, vote_rank FROM {$wpdb->prefix}pf_votes WHERE show_slug = %s ORDER BY vote_rank ASC",
            self::SHOW
        ));
        return array_map(static function ($row) {
            return ['performer_id' => (int) $row->performer_id, 'vote_rank' => (int) $row->vote_rank];
        }, $rows);
    }

    public function test_valid_ranked_ballot_is_recorded_in_order(): void {
        $response = $this->submit([13, 11]);

        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        $this->assertSame([
            ['performer_id' => 13, 'vote_rank' => 1],
            ['performer_id' => 11, 'vote_rank' => 2],
        ], $this->rows());
    }

    public function test_repeated_performer_is_counted_once(): void {
        $response = $this->submit([11, 11, 11]);

        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        $this->assertSame([['performer_id' => 11, 'vote_rank' => 1]], $this->rows());
    }

    public function test_performer_outside_the_active_group_is_rejected(): void {
        $response = $this->submit([11, 21]);

        $this->assertSame(400, $response->get_status());
        $this->assertSame([], $this->rows());
    }

    public function test_unknown_performer_is_rejected(): void {
        $this->assertSame(400, $this->submit([999])->get_status());
        $this->assertSame([], $this->rows());
    }

    public function test_more_than_three_choices_is_rejected(): void {
        $response = $this->submit([11, 12, 13, 14]);

        $this->assertSame(400, $response->get_status());
        $this->assertSame([], $this->rows());
    }

    public function test_non_numeric_or_empty_ballot_is_rejected(): void {
        $this->assertSame(400, $this->submit(['abc', -5])->get_status());
        $this->assertSame(400, $this->submit([])->get_status());
        $this->assertSame([], $this->rows());
    }

    public function test_second_ballot_from_same_voter_is_rejected(): void {
        $this->assertSame(200, $this->submit([11], 'tok-a')->get_status());
        $this->assertSame(403, $this->submit([12], 'tok-b')->get_status(), 'already_voted is a 403 by contract.');
        $this->assertCount(1, $this->rows());
    }

    public function test_database_enforces_one_ballot_per_voter_per_group(): void {
        $this->assertSame(200, $this->submit([11, 12])->get_status());

        global $wpdb;
        $table = "{$wpdb->prefix}pf_votes";
        $first = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE show_slug = %s AND vote_rank = 1",
            self::SHOW
        ), ARRAY_A);

        $this->assertIsArray($first);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) ($first['ballot_key'] ?? ''));

        // A concurrent duplicate that slipped past the pre-check must be refused by MySQL.
        unset($first['id']);
        $first['token'] = 'racing-token';
        $suppress = $wpdb->suppress_errors(true);
        $inserted = $wpdb->insert($table, $first);
        $wpdb->suppress_errors($suppress);

        $this->assertFalse($inserted, '(ballot_key, vote_rank) must be UNIQUE.');
    }

    public function test_administrators_keep_their_unrestricted_test_votes(): void {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $this->assertSame(200, $this->submit([11])->get_status());
        $this->assertSame(200, $this->submit([12])->get_status());
        $this->assertCount(2, $this->rows());
    }
}
