<?php
/**
 * Real-WordPress contract test: integration secrets are never returned by the
 * admin settings API, and only administrators can change them.
 *
 * Regression: GET/PUT /peanut-festival/v1/admin/settings allowed
 * `manage_options || manage_pf_festival`, and the Festival Producer role has
 * `manage_pf_festival`. GET returned the Stripe live/test secret keys, the
 * Stripe webhook secret, the Firebase service-account private key and the
 * Mailchimp/Eventbrite/Booker/ML keys in plaintext; PUT merged any JSON into
 * the settings option, so a producer could swap the Stripe keys.
 */

namespace Peanut_Festival\Tests\ContractWp;

use WP_UnitTestCase;
use WP_REST_Request;

class AdminSettingsSecretsTest extends WP_UnitTestCase {

    /** @var array<string,string> */
    private const SECRETS = [
        'stripe_live_secret_key' => 'sk_live_REALSECRETVALUE1234',
        'stripe_test_secret_key' => 'sk_test_REALSECRETVALUE5678',
        'stripe_webhook_secret' => 'whsec_REALWEBHOOKSECRET9999',
        'firebase_service_account' => '{"type":"service_account","private_key":"-----BEGIN PRIVATE KEY-----\nMIIEsecret\n-----END PRIVATE KEY-----\n"}',
        'mailchimp_api_key' => 'mailchimpkeyvalue0000-us21',
        'eventbrite_token' => 'EVENTBRITEPRIVATETOKEN4321',
        'booker_api_key' => 'BOOKERAPIKEYVALUE0007',
        'ml_api_key' => 'MLAPIKEYVALUE00000042',
    ];

    private int $producer = 0;
    private int $admin = 0;

    public function set_up(): void {
        parent::set_up();

        \Peanut_Festival_Activator::activate();

        update_option('peanut_festival_settings', array_merge(self::SECRETS, [
            'notification_email' => 'ops@example.org',
            'stripe_live_publishable_key' => 'pk_live_PUBLISHABLE0001',
            'stripe_test_mode' => 0,
            'booker_api_url' => 'https://booker.example.org',
            'voting_weight_first' => 3,
        ]));

        $this->producer = self::factory()->user->create(['role' => 'pf_producer']);
        $this->admin = self::factory()->user->create(['role' => 'administrator']);

        global $wp_rest_server;
        $wp_rest_server = null;
        do_action('rest_api_init');
    }

    private function get_settings(int $user): \WP_REST_Response {
        wp_set_current_user($user);
        return rest_get_server()->dispatch(new WP_REST_Request('GET', '/peanut-festival/v1/admin/settings'));
    }

    /**
     * @param array<string,mixed> $body
     */
    private function put_settings(int $user, array $body): \WP_REST_Response {
        wp_set_current_user($user);
        $request = new WP_REST_Request('PUT', '/peanut-festival/v1/admin/settings');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(wp_json_encode($body));
        return rest_get_server()->dispatch($request);
    }

    /**
     * @return array<string,mixed>
     */
    private function stored(): array {
        return (array) get_option('peanut_festival_settings', []);
    }

    public function test_producer_still_has_access_to_the_settings_screen(): void {
        $this->assertTrue(user_can($this->producer, 'manage_pf_festival'));
        $this->assertFalse(user_can($this->producer, 'manage_options'));
        $this->assertSame(200, $this->get_settings($this->producer)->get_status());
    }

    public function test_get_never_returns_a_secret_in_plaintext(): void {
        foreach ([$this->producer, $this->admin] as $user) {
            $response = $this->get_settings($user);
            $this->assertSame(200, $response->get_status());
            $json = wp_json_encode($response->get_data());

            foreach (self::SECRETS as $key => $secret) {
                $this->assertStringNotContainsString(
                    $secret,
                    $json,
                    "$key must not be returned in plaintext (user $user)."
                );
            }
            $this->assertStringNotContainsString('BEGIN PRIVATE KEY', $json);
        }
    }

    public function test_get_returns_a_mask_and_has_value_flag(): void {
        $data = $this->get_settings($this->producer)->get_data()['data'];

        $this->assertSame('••••1234', $data['stripe_live_secret_key']);
        $this->assertSame('••••', $data['firebase_service_account']);
        $this->assertTrue($data['_secrets']['stripe_live_secret_key']['has_value']);
        $this->assertFalse($data['_secrets']['eventbrite_client_secret']['has_value']);

        // Non-secret values are still returned as-is.
        $this->assertSame('ops@example.org', $data['notification_email']);
        $this->assertSame('pk_live_PUBLISHABLE0001', $data['stripe_live_publishable_key']);
    }

    public function test_producer_cannot_change_a_secret(): void {
        $response = $this->put_settings($this->producer, ['stripe_live_secret_key' => 'sk_live_ATTACKER']);

        $this->assertSame(403, $response->get_status());
        $this->assertSame(self::SECRETS['stripe_live_secret_key'], $this->stored()['stripe_live_secret_key']);
    }

    public function test_producer_cannot_redirect_payments_or_integrations(): void {
        foreach ([
            'stripe_live_publishable_key' => 'pk_live_ATTACKER',
            'stripe_test_mode' => true,
            'booker_api_url' => 'https://attacker.example',
            'trusted_proxies' => '0.0.0.0/0',
        ] as $key => $value) {
            $before = $this->stored()[$key] ?? null;
            $response = $this->put_settings($this->producer, [$key => $value]);

            $this->assertSame(403, $response->get_status(), "$key must be administrator-only.");
            $this->assertSame($before, $this->stored()[$key] ?? null);
        }
    }

    public function test_producer_can_save_the_form_with_masked_secrets_echoed_back(): void {
        $form = $this->get_settings($this->producer)->get_data()['data'];
        unset($form['_secrets']);
        $form['notification_email'] = 'producer@example.org';

        $response = $this->put_settings($this->producer, $form);

        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        $stored = $this->stored();
        $this->assertSame('producer@example.org', $stored['notification_email']);
        foreach (self::SECRETS as $key => $secret) {
            $this->assertSame($secret, $stored[$key], "$key must survive a masked round-trip.");
        }
    }

    public function test_admin_can_change_secrets_and_admin_only_settings(): void {
        $response = $this->put_settings($this->admin, [
            'stripe_live_secret_key' => 'sk_live_NEWVALUE00000001',
            'stripe_test_mode' => true,
            'trusted_proxies' => '10.0.0.0/8',
        ]);

        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        $stored = $this->stored();
        $this->assertSame('sk_live_NEWVALUE00000001', $stored['stripe_live_secret_key']);
        $this->assertTrue((bool) $stored['stripe_test_mode']);
        $this->assertSame('10.0.0.0/8', $stored['trusted_proxies']);
    }

    public function test_unknown_keys_are_ignored(): void {
        $response = $this->put_settings($this->admin, [
            'firebase_credentials_file' => '/etc/passwd',
            'totally_new_key' => 'x',
        ]);

        $this->assertSame(200, $response->get_status());
        $stored = $this->stored();
        $this->assertArrayNotHasKey('firebase_credentials_file', $stored);
        $this->assertArrayNotHasKey('totally_new_key', $stored);
    }

    public function test_writable_values_are_sanitised(): void {
        $response = $this->put_settings($this->producer, [
            'voting_weight_first' => '5<script>',
            'mailchimp_list_id' => '<b>abc123</b>',
        ]);

        $this->assertSame(200, $response->get_status());
        $stored = $this->stored();
        $this->assertSame(5, $stored['voting_weight_first']);
        $this->assertSame('abc123', $stored['mailchimp_list_id']);
    }

    public function test_producer_cannot_upload_firebase_service_account(): void {
        wp_set_current_user($this->producer);
        $request = new WP_REST_Request('PUT', '/peanut-festival/v1/admin/firebase/settings');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(wp_json_encode([
            'credentials_json' => base64_encode(wp_json_encode([
                'type' => 'service_account',
                'project_id' => 'attacker-project',
                'private_key_id' => 'abc',
                'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIE\n-----END PRIVATE KEY-----\n",
                'client_email' => 'svc@attacker-project.iam.gserviceaccount.com',
            ])),
        ]));

        $response = rest_get_server()->dispatch($request);

        $this->assertSame(403, $response->get_status());
        $this->assertArrayNotHasKey('firebase_credentials_file', $this->stored());
    }
}
