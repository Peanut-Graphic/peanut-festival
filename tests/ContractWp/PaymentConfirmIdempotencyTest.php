<?php
/**
 * Real-WordPress contract test: one Stripe PaymentIntent yields exactly one ticket.
 *
 * Regression for the "one paid ticket -> unlimited tickets" finding:
 * `POST /peanut-festival/v1/payments/confirm` is public, and every call for a
 * PaymentIntent Stripe reports as `succeeded` used to insert a brand-new ticket
 * (plus a transaction row and a confirmation email). Replaying the confirm call
 * minted unlimited tickets from a single payment.
 *
 * Runs against real MySQL so the UNIQUE index and the named lock are exercised
 * for real. Stripe is stubbed through `pre_http_request` — no network.
 */

namespace Peanut_Festival\Tests\ContractWp;

use WP_UnitTestCase;
use WP_REST_Request;

class PaymentConfirmIdempotencyTest extends WP_UnitTestCase {

    private const PI_ID = 'pi_3TestIdempotency0001';

    /** @var array<string,mixed> */
    private array $stripe_intent = [];

    public function set_up(): void {
        parent::set_up();

        \Peanut_Festival_Activator::activate();
        \Peanut_Festival_Migrations::run();

        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}pf_tickets");
        $wpdb->query("DELETE FROM {$wpdb->prefix}pf_transactions");
        $wpdb->query("DELETE FROM {$wpdb->prefix}pf_attendees");

        \Peanut_Festival_Settings::update([
            'stripe_test_mode' => 1,
            'stripe_test_secret_key' => 'sk_test_contract',
            'stripe_test_publishable_key' => 'pk_test_contract',
        ]);

        $this->stripe_intent = [
            'id' => self::PI_ID,
            'object' => 'payment_intent',
            'status' => 'succeeded',
            'amount' => 2500,
            'metadata' => [
                'show_id' => '7',
                'quantity' => '2',
                'customer_name' => 'Ada Lovelace',
                'customer_email' => 'ada@example.com',
                'customer_phone' => '',
                'festival_id' => '1',
            ],
        ];

        add_filter('pre_http_request', [$this, 'fake_stripe'], 10, 3);

        global $wp_rest_server;
        $wp_rest_server = null;
        do_action('rest_api_init');
    }

    public function tear_down(): void {
        remove_filter('pre_http_request', [$this, 'fake_stripe'], 10);
        parent::tear_down();
    }

    /**
     * @param mixed  $pre
     * @param array  $args
     * @param string $url
     * @return mixed
     */
    public function fake_stripe($pre, $args, $url) {
        if (strpos($url, 'https://api.stripe.com/v1/payment_intents/') !== 0) {
            return $pre;
        }
        return [
            'headers' => [],
            'body' => wp_json_encode($this->stripe_intent),
            'response' => ['code' => 200, 'message' => 'OK'],
            'cookies' => [],
            'filename' => null,
        ];
    }

    private function confirm(string $payment_intent_id = self::PI_ID): \WP_REST_Response {
        $request = new WP_REST_Request('POST', '/peanut-festival/v1/payments/confirm');
        $request->set_param('payment_intent_id', $payment_intent_id);
        return rest_get_server()->dispatch($request);
    }

    private function ticket_count(string $payment_id = self::PI_ID): int {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}pf_tickets WHERE payment_id = %s",
            $payment_id
        ));
    }

    /**
     * Open an independent MySQL session (a stand-in for a concurrent request).
     */
    private function second_connection(): \mysqli {
        $host = DB_HOST;
        $port = 3306;
        if (strpos($host, ':') !== false) {
            [$host, $port] = explode(':', $host, 2);
            $port = (int) $port;
        }
        return new \mysqli($host, DB_USER, DB_PASSWORD, DB_NAME, $port);
    }

    public function test_replayed_confirm_returns_the_same_single_ticket(): void {
        $first = $this->confirm();
        $this->assertSame(200, $first->get_status(), wp_json_encode($first->get_data()));
        $first_data = $first->get_data()['data'];

        for ($i = 0; $i < 4; $i++) {
            $again = $this->confirm();
            $this->assertSame(200, $again->get_status(), wp_json_encode($again->get_data()));
            $this->assertSame($first_data['ticket_id'], $again->get_data()['data']['ticket_id']);
            $this->assertSame($first_data['ticket_code'], $again->get_data()['data']['ticket_code']);
        }

        $this->assertSame(1, $this->ticket_count(), 'A PaymentIntent must never produce more than one ticket.');

        global $wpdb;
        $transactions = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}pf_transactions WHERE reference = %s",
            self::PI_ID
        ));
        $this->assertSame(1, $transactions, 'Revenue must be recorded once per PaymentIntent.');
    }

    public function test_confirm_while_another_request_holds_the_payment_lock_does_not_create_a_ticket(): void {
        $other = $this->second_connection();
        $lock = \Peanut_Festival_Payments::lock_name(self::PI_ID);
        $got = $other->query("SELECT GET_LOCK('" . $other->real_escape_string($lock) . "', 0)")->fetch_row()[0];
        $this->assertSame('1', (string) $got);
        add_filter('peanut_festival_payment_lock_timeout', '__return_zero');

        try {
            $response = $this->confirm();
            $this->assertSame(409, $response->get_status(), wp_json_encode($response->get_data()));
            $this->assertSame(0, $this->ticket_count());
        } finally {
            $other->query("SELECT RELEASE_LOCK('" . $other->real_escape_string($lock) . "')");
            $other->close();
        }

        // Once the lock is free the confirm completes normally — exactly once.
        $this->assertSame(200, $this->confirm()->get_status());
        $this->assertSame(200, $this->confirm()->get_status());
        $this->assertSame(1, $this->ticket_count());
    }

    public function test_webhook_and_confirm_paths_share_one_ticket(): void {
        $method = new \ReflectionMethod(\Peanut_Festival_Payments::class, 'handle_payment_succeeded');
        $method->invoke(null, $this->stripe_intent);
        $method->invoke(null, $this->stripe_intent);

        $this->assertSame(200, $this->confirm()->get_status());
        $this->assertSame(1, $this->ticket_count());
    }

    public function test_database_rejects_a_second_ticket_for_the_same_payment(): void {
        global $wpdb;
        $row = [
            'attendee_id' => 1,
            'show_id' => 7,
            'quantity' => 1,
            'ticket_code' => 'AAAA1111',
            'payment_id' => 'pi_unique_guard',
            'payment_status' => 'completed',
        ];
        $this->assertSame(1, $wpdb->insert("{$wpdb->prefix}pf_tickets", $row));

        $suppress = $wpdb->suppress_errors(true);
        $row['ticket_code'] = 'BBBB2222';
        $second = $wpdb->insert("{$wpdb->prefix}pf_tickets", $row);
        $wpdb->suppress_errors($suppress);

        $this->assertFalse($second, 'tickets.payment_id must be UNIQUE.');
    }

    public function test_tickets_without_a_payment_id_are_not_constrained(): void {
        global $wpdb;
        foreach (['CCCC3333', 'DDDD4444'] as $code) {
            $this->assertSame(1, $wpdb->insert("{$wpdb->prefix}pf_tickets", [
                'attendee_id' => 1,
                'show_id' => 7,
                'quantity' => 1,
                'ticket_code' => $code,
                'payment_id' => null,
            ]));
        }
    }

    public function test_malformed_payment_intent_id_is_rejected_before_calling_stripe(): void {
        $called = false;
        $spy = static function ($pre) use (&$called) {
            $called = true;
            return $pre;
        };
        add_filter('pre_http_request', $spy, 1);

        $response = $this->confirm('pi_123/../../customers');

        remove_filter('pre_http_request', $spy, 1);
        $this->assertSame(400, $response->get_status());
        $this->assertFalse($called, 'A malformed id must never be interpolated into a Stripe API path.');
    }
}
