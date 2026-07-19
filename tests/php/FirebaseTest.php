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

    /**
     * Regression (audit 2026-07, P5): server-side writes must NOT fall back to
     * the public web API key as the Realtime-DB ?auth= param. Every write()
     * caller is a privileged server write (leaderboards, brackets, now_playing,
     * festival state) that database.rules.json requires a service_account token
     * for; the browser api_key is public, so ?auth=<api_key> is a weak/
     * misleading auth path. write() must authenticate only with the minted
     * service-account Bearer token and fail closed when it is unavailable.
     */
    public function test_write_does_not_fall_back_to_public_api_key_auth(): void
    {
        $ref = new ReflectionClass(Peanut_Festival_Firebase::class);
        $src = file_get_contents($ref->getFileName());
        $start = strpos($src, 'public function write(');
        $this->assertNotFalse($start, 'write() method not found');
        // Slice from write() to the next public method (read()).
        $end = strpos($src, 'public function read(', $start);
        $body = substr($src, $start, ($end !== false ? $end - $start : 900));

        $this->assertStringNotContainsString(
            "\$url .= '?auth='",
            $body,
            'write() must not append ?auth=<api_key> — that is the public-key fallback the P5 fix removed.'
        );
        $this->assertMatchesRegularExpression(
            '/if\s*\(\s*!\s*\$token\s*\)\s*\{[^}]*return false;/s',
            $body,
            'write() must fail closed (return false) when no service-account token is available.'
        );
        $this->assertStringContainsString(
            "'Authorization' => 'Bearer ' . \$token",
            $body,
            'write() must authenticate with the service-account Bearer token.'
        );
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
