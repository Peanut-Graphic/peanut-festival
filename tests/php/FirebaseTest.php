<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Tests for the Firebase integration class
 */

use PHPUnit\Framework\TestCase;

class FirebaseTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // Reset mock options for each test
        global $mock_options;
        $mock_options = [];
        // Reset the singleton so each test re-reads its own settings.
        Peanut_Festival_Firebase::reset_instance();
    }

    public function test_is_enabled_returns_false_when_not_configured(): void
    {
        global $mock_options;
        $mock_options['peanut_festival_settings'] = [];

        // Firebase should be disabled when not configured
        $enabled = Peanut_Festival_Firebase::is_enabled();

        $this->assertFalse($enabled);
    }

    public function test_get_client_config_returns_disabled_when_not_configured(): void
    {
        global $mock_options;
        $mock_options['peanut_festival_settings'] = [
            'firebase_enabled' => false,
        ];

        $config = Peanut_Festival_Firebase::get_client_config();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled', $config);
        $this->assertFalse($config['enabled']);
    }

    public function test_get_client_config_excludes_service_account(): void
    {
        global $mock_options;
        $mock_options['peanut_festival_settings'] = [
            'firebase_enabled' => true,
            'firebase_project_id' => 'test-project',
            'firebase_database_url' => 'https://test.firebaseio.com',
            'firebase_api_key' => 'test-api-key',
            'firebase_service_account' => '{"private_key": "secret"}',
        ];

        $config = Peanut_Festival_Firebase::get_client_config();

        // Service account should never be exposed to client
        $this->assertArrayNotHasKey('serviceAccount', $config);
        $this->assertArrayNotHasKey('service_account', $config);
    }

    public function test_get_client_config_includes_required_fields(): void
    {
        global $mock_options;
        $mock_options['peanut_festival_settings'] = [
            'firebase_enabled' => true,
            'firebase_project_id' => 'test-project',
            'firebase_database_url' => 'https://test.firebaseio.com',
            'firebase_api_key' => 'test-api-key',
            'firebase_vapid_key' => 'test-vapid-key',
        ];

        $config = Peanut_Festival_Firebase::get_client_config();

        $this->assertArrayHasKey('projectId', $config);
        $this->assertArrayHasKey('databaseURL', $config);
        $this->assertArrayHasKey('apiKey', $config);
        $this->assertArrayHasKey('vapidKey', $config);
    }

    public function test_singleton_returns_same_instance(): void
    {
        $instance1 = Peanut_Festival_Firebase::get_instance();
        $instance2 = Peanut_Festival_Firebase::get_instance();

        $this->assertSame($instance1, $instance2);
    }

    /**
     * Seed a fully-enabled Firebase config and (re)build the singleton so its
     * hooks register. Returns the fresh instance.
     */
    private function enable_firebase(): Peanut_Festival_Firebase
    {
        global $mock_options, $registered_actions;
        $mock_options['peanut_festival_settings'] = [
            'firebase_enabled' => true,
            'firebase_project_id' => 'test-project',
            'firebase_database_url' => 'https://test.firebaseio.com',
            'firebase_api_key' => 'test-api-key',
            'firebase_service_account' => '',
        ];
        $registered_actions = [];
        Peanut_Festival_Firebase::reset_instance();
        return Peanut_Festival_Firebase::get_instance();
    }

    public function test_vote_recorded_is_not_double_hooked_to_blocking_sync(): void
    {
        // The batched Realtime_Sync layer owns the vote_recorded -> Firebase
        // write. Firebase must NOT also hook its own blocking per-vote
        // sync_vote() to the same event, or every vote does TWO Firebase
        // writes (one of them a synchronous 10s-timeout REST call inline).
        $firebase = $this->enable_firebase();

        $this->assertFalse(
            has_action('peanut_festival_vote_recorded', [$firebase, 'sync_vote']),
            'Firebase::sync_vote must not be hooked to peanut_festival_vote_recorded '
            . '(duplicates the batched Realtime_Sync write)'
        );
    }

    public function test_data_sync_events_are_not_hooked_to_blocking_firebase_methods(): void
    {
        $firebase = $this->enable_firebase();

        // None of the batched data-sync events should route to Firebase's own
        // inline blocking sync_* handlers.
        $this->assertFalse(has_action('peanut_festival_vote_recorded', [$firebase, 'sync_vote']));
        $this->assertFalse(has_action('peanut_festival_match_vote_recorded', [$firebase, 'sync_match_vote']));
        $this->assertFalse(has_action('peanut_festival_show_status_changed', [$firebase, 'sync_show_status']));
        $this->assertFalse(has_action('peanut_festival_performer_checkin', [$firebase, 'sync_performer_checkin']));
    }

    public function test_push_notification_hooks_are_still_registered(): void
    {
        // Push-notification triggers are unique to Firebase (not duplicated in
        // Realtime_Sync) and must remain wired.
        $firebase = $this->enable_firebase();

        $this->assertTrue(has_action('peanut_festival_voting_starting', [$firebase, 'notify_voting_starting']));
        $this->assertTrue(has_action('peanut_festival_performer_on_stage', [$firebase, 'notify_performer_on_stage']));
        $this->assertTrue(has_action('peanut_festival_winner_announced', [$firebase, 'notify_winner_announced']));
    }

    public function test_access_token_is_persisted_in_transient(): void
    {
        global $transients;
        $transients = [];

        $firebase = $this->enable_firebase();

        // Build a service account so token acquisition is attempted.
        $reflection = new ReflectionClass(Peanut_Festival_Firebase::class);
        $configProp = $reflection->getProperty('config');
        $configProp->setAccessible(true);
        $config = $configProp->getValue($firebase);
        $config['service_account'] = json_encode([
            'client_email' => 'svc@test.iam.gserviceaccount.com',
            'private_key' => self::test_private_key(),
        ]);
        $configProp->setValue($firebase, $config);

        $method = $reflection->getMethod('get_access_token');
        $method->setAccessible(true);
        $token = $method->invoke($firebase);

        // The HTTP exchange is mocked to return a token (see bootstrap), so the
        // result should be cached in a transient for reuse across requests.
        $this->assertNotEmpty($token, 'Expected an access token from the mocked exchange');

        $cached = false;
        foreach ($transients as $key => $value) {
            if (strpos($key, 'firebase') !== false && strpos($key, 'token') !== false) {
                $cached = true;
                break;
            }
        }
        $this->assertTrue(
            $cached,
            'OAuth access token must be persisted in a transient so it survives '
            . 'across requests (instance-only caching forces a blocking OAuth '
            . 'roundtrip on the first Firebase write of every request)'
        );
    }

    /**
     * A throwaway RSA private key generated for tests only.
     */
    private static function test_private_key(): string
    {
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($res, $pem);
        return $pem;
    }

    public function test_write_returns_false_when_disabled(): void
    {
        global $mock_options;
        $mock_options['peanut_festival_settings'] = [
            'firebase_enabled' => false,
        ];

        // Reset instance to reload config
        $reflection = new ReflectionClass(Peanut_Festival_Firebase::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, null);

        $firebase = Peanut_Festival_Firebase::get_instance();
        $result = $firebase->write('test/path', ['data' => 'value']);

        $this->assertFalse($result);
    }

    public function test_read_returns_null_when_disabled(): void
    {
        global $mock_options;
        $mock_options['peanut_festival_settings'] = [
            'firebase_enabled' => false,
        ];

        // Reset instance
        $reflection = new ReflectionClass(Peanut_Festival_Firebase::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, null);

        $firebase = Peanut_Festival_Firebase::get_instance();
        $result = $firebase->read('test/path');

        $this->assertNull($result);
    }

    public function test_delete_returns_false_when_disabled(): void
    {
        global $mock_options;
        $mock_options['peanut_festival_settings'] = [
            'firebase_enabled' => false,
        ];

        // Reset instance
        $reflection = new ReflectionClass(Peanut_Festival_Firebase::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, null);

        $firebase = Peanut_Festival_Firebase::get_instance();
        $result = $firebase->delete('test/path');

        $this->assertFalse($result);
    }

    public function test_send_notification_returns_false_when_disabled(): void
    {
        global $mock_options;
        $mock_options['peanut_festival_settings'] = [
            'firebase_enabled' => false,
        ];

        // Reset instance
        $reflection = new ReflectionClass(Peanut_Festival_Firebase::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, null);

        $firebase = Peanut_Festival_Firebase::get_instance();
        $result = $firebase->send_notification('test-topic', [
            'title' => 'Test',
            'body' => 'Test message',
        ]);

        $this->assertFalse($result);
    }

    public function test_subscribe_to_topic_returns_false_when_disabled(): void
    {
        global $mock_options;
        $mock_options['peanut_festival_settings'] = [
            'firebase_enabled' => false,
        ];

        // Reset instance
        $reflection = new ReflectionClass(Peanut_Festival_Firebase::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, null);

        $firebase = Peanut_Festival_Firebase::get_instance();
        $result = $firebase->subscribe_to_topic('device-token', 'festival_1');

        $this->assertFalse($result);
    }

    public function test_jwt_header_structure(): void
    {
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $this->assertEquals('RS256', $header['alg']);
        $this->assertEquals('JWT', $header['typ']);
    }

    public function test_jwt_claims_structure(): void
    {
        $client_email = 'test@project.iam.gserviceaccount.com';
        $now = time();

        $claims = [
            'iss' => $client_email,
            'scope' => 'https://www.googleapis.com/auth/firebase.database https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $this->assertEquals($client_email, $claims['iss']);
        $this->assertStringContainsString('firebase.database', $claims['scope']);
        $this->assertStringContainsString('firebase.messaging', $claims['scope']);
        $this->assertEquals('https://oauth2.googleapis.com/token', $claims['aud']);
        $this->assertEquals($now, $claims['iat']);
        $this->assertEquals($now + 3600, $claims['exp']);
    }

    public function test_base64url_encoding(): void
    {
        // Test the base64url encoding format (no padding, URL-safe chars)
        $data = '{"test": "data"}';
        $encoded = rtrim(strtr(base64_encode($data), '+/', '-_'), '=');

        $this->assertStringNotContainsString('+', $encoded);
        $this->assertStringNotContainsString('/', $encoded);
        $this->assertStringNotContainsString('=', $encoded);
    }

    public function test_notification_message_structure(): void
    {
        $topic = 'festival_123';
        $notification = [
            'title' => 'Test Title',
            'body' => 'Test body message',
            'icon' => '/images/icon.png',
        ];
        $data = ['type' => 'test', 'id' => '123'];

        $message = [
            'message' => [
                'topic' => $topic,
                'notification' => $notification,
                'data' => array_map('strval', $data),
            ],
        ];

        $this->assertEquals('festival_123', $message['message']['topic']);
        $this->assertEquals('Test Title', $message['message']['notification']['title']);
        $this->assertEquals('Test body message', $message['message']['notification']['body']);
        $this->assertEquals('test', $message['message']['data']['type']);
        $this->assertEquals('123', $message['message']['data']['id']);
    }

    public function test_database_path_sanitization(): void
    {
        $path = 'votes/test-show/group_a';
        $sanitized = sanitize_key($path);

        // sanitize_key removes slashes, so we use it on path segments
        $segments = explode('/', $path);
        $sanitized_segments = array_map('sanitize_key', $segments);

        $this->assertEquals('votes', $sanitized_segments[0]);
        $this->assertEquals('test-show', $sanitized_segments[1]);
        $this->assertEquals('group_a', $sanitized_segments[2]);
    }

    public function test_firebase_sync_data_structure(): void
    {
        // Test the structure of data synced to Firebase
        $vote_data = [
            'performer_id' => 1,
            'name' => 'Test Performer',
            'score' => 100,
            'votes' => 10,
        ];

        $meta = [
            'updated_at' => gmdate('c'),
            'total_votes' => 10,
        ];

        $this->assertArrayHasKey('performer_id', $vote_data);
        $this->assertArrayHasKey('score', $vote_data);
        $this->assertArrayHasKey('votes', $vote_data);
        $this->assertIsInt($vote_data['score']);
        $this->assertIsInt($vote_data['votes']);
        $this->assertArrayHasKey('updated_at', $meta);
        $this->assertArrayHasKey('total_votes', $meta);
    }

    public function test_register_routes_method_exists(): void
    {
        $this->assertTrue(method_exists(Peanut_Festival_Firebase::class, 'register_routes'));
    }

    public function test_api_get_config_returns_rest_response(): void
    {
        $response = Peanut_Festival_Firebase::api_get_config();

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('config', $data);
    }

    public function test_topic_name_format(): void
    {
        $festival_id = 123;
        $topic = 'festival_' . $festival_id;

        $this->assertEquals('festival_123', $topic);
        $this->assertMatchesRegularExpression('/^festival_\d+$/', $topic);
    }

    public function test_token_expiration_calculation(): void
    {
        $now = time();
        $expires_in = 3600; // 1 hour
        $buffer = 60; // 1 minute buffer

        $token_expires = $now + $expires_in - $buffer;

        $this->assertLessThan($now + $expires_in, $token_expires);
        $this->assertGreaterThan($now, $token_expires);
    }
}
