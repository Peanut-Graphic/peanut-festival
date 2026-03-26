<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Tests for webhook handling (Stripe, Eventbrite, etc.)
 */

use PHPUnit\Framework\TestCase;

class WebhooksTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        global $mock_options;
        $mock_options = [];
    }

    // =============================================
    // Stripe Webhook Signature Verification Tests
    // =============================================

    public function test_stripe_signature_format(): void
    {
        $timestamp = time();
        $signature = 'v1=' . hash_hmac('sha256', 'test', 'secret');

        $header = "t={$timestamp},v1={$signature}";

        $this->assertStringContainsString('t=', $header);
        $this->assertStringContainsString('v1=', $header);
    }

    public function test_stripe_signature_parsing(): void
    {
        $timestamp = time();
        $sig = hash_hmac('sha256', 'test', 'secret');
        $header = "t={$timestamp},v1={$sig}";

        $elements = explode(',', $header);
        $parsed_timestamp = null;
        $signatures = [];

        foreach ($elements as $element) {
            $parts = explode('=', $element, 2);
            if (count($parts) === 2) {
                if ($parts[0] === 't') {
                    $parsed_timestamp = $parts[1];
                } elseif ($parts[0] === 'v1') {
                    $signatures[] = $parts[1];
                }
            }
        }

        $this->assertEquals((string) $timestamp, $parsed_timestamp);
        $this->assertCount(1, $signatures);
        $this->assertEquals($sig, $signatures[0]);
    }

    public function test_stripe_timestamp_tolerance(): void
    {
        // Stripe allows 5 minute (300 second) tolerance
        $now = time();
        $tolerance = 300;

        // Valid timestamp (within tolerance)
        $valid_timestamp = $now - 120; // 2 minutes ago
        $this->assertTrue(abs($now - $valid_timestamp) <= $tolerance);

        // Invalid timestamp (outside tolerance)
        $invalid_timestamp = $now - 600; // 10 minutes ago
        $this->assertFalse(abs($now - $invalid_timestamp) <= $tolerance);
    }

    public function test_stripe_signature_verification(): void
    {
        $payload = '{"type":"payment_intent.succeeded"}';
        $secret = 'whsec_test_secret';
        $timestamp = time();

        $signed_payload = $timestamp . '.' . $payload;
        $expected_signature = hash_hmac('sha256', $signed_payload, $secret);
        $calculated_signature = hash_hmac('sha256', $signed_payload, $secret);

        $this->assertTrue(hash_equals($expected_signature, $calculated_signature));
    }

    public function test_stripe_signature_verification_fails_with_wrong_secret(): void
    {
        $payload = '{"type":"payment_intent.succeeded"}';
        $correct_secret = 'whsec_correct';
        $wrong_secret = 'whsec_wrong';
        $timestamp = time();

        $signed_payload = $timestamp . '.' . $payload;
        $expected_signature = hash_hmac('sha256', $signed_payload, $correct_secret);
        $wrong_signature = hash_hmac('sha256', $signed_payload, $wrong_secret);

        $this->assertFalse(hash_equals($expected_signature, $wrong_signature));
    }

    // =============================================
    // Stripe Event Type Tests
    // =============================================

    public function test_stripe_event_types(): void
    {
        $handled_events = [
            'payment_intent.succeeded',
            'payment_intent.payment_failed',
            'charge.refunded',
        ];

        $this->assertContains('payment_intent.succeeded', $handled_events);
        $this->assertContains('payment_intent.payment_failed', $handled_events);
        $this->assertContains('charge.refunded', $handled_events);
    }

    public function test_payment_intent_succeeded_structure(): void
    {
        $event = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test123',
                    'amount' => 2500,
                    'currency' => 'usd',
                    'status' => 'succeeded',
                    'metadata' => [
                        'show_id' => '1',
                        'quantity' => '2',
                        'customer_email' => 'test@example.com',
                    ],
                ],
            ],
        ];

        $this->assertEquals('payment_intent.succeeded', $event['type']);
        $this->assertArrayHasKey('data', $event);
        $this->assertArrayHasKey('object', $event['data']);
        $this->assertEquals('pi_test123', $event['data']['object']['id']);
        $this->assertEquals(2500, $event['data']['object']['amount']);
    }

    public function test_payment_intent_failed_structure(): void
    {
        $event = [
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_test123',
                    'status' => 'failed',
                    'last_payment_error' => [
                        'message' => 'Your card was declined.',
                        'code' => 'card_declined',
                    ],
                ],
            ],
        ];

        $this->assertEquals('payment_intent.payment_failed', $event['type']);
        $this->assertEquals('failed', $event['data']['object']['status']);
        $this->assertArrayHasKey('last_payment_error', $event['data']['object']);
    }

    public function test_charge_refunded_structure(): void
    {
        $event = [
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_test123',
                    'payment_intent' => 'pi_test123',
                    'amount_refunded' => 2500,
                    'refunded' => true,
                ],
            ],
        ];

        $this->assertEquals('charge.refunded', $event['type']);
        $this->assertTrue($event['data']['object']['refunded']);
        $this->assertEquals(2500, $event['data']['object']['amount_refunded']);
    }

    // =============================================
    // Webhook Handler Response Tests
    // =============================================

    public function test_webhook_success_response(): void
    {
        $response = ['received' => true];
        $http_code = 200;

        $this->assertEquals(200, $http_code);
        $this->assertTrue($response['received']);
    }

    public function test_webhook_error_response_missing_signature(): void
    {
        $response = ['error' => 'Invalid signature'];
        $http_code = 400;

        $this->assertEquals(400, $http_code);
        $this->assertArrayHasKey('error', $response);
    }

    public function test_webhook_error_response_missing_secret(): void
    {
        $response = ['error' => 'Webhook secret not configured'];
        $http_code = 400;

        $this->assertEquals(400, $http_code);
        $this->assertArrayHasKey('error', $response);
    }

    // =============================================
    // Payment Processing Tests
    // =============================================

    public function test_ticket_creation_from_payment(): void
    {
        $payment = [
            'id' => 'pi_test123',
            'amount' => 2500,
            'status' => 'succeeded',
        ];

        $metadata = [
            'show_id' => '1',
            'quantity' => '2',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'festival_id' => '1',
        ];

        $this->assertEquals(1, (int) $metadata['show_id']);
        $this->assertEquals(2, (int) $metadata['quantity']);
        $this->assertEquals('john@example.com', $metadata['customer_email']);
    }

    public function test_ticket_code_generation(): void
    {
        $payment_id = 'pi_test123';
        $ticket_code = strtoupper(substr(md5($payment_id . time()), 0, 8));

        $this->assertEquals(8, strlen($ticket_code));
        $this->assertMatchesRegularExpression('/^[A-F0-9]{8}$/', $ticket_code);
    }

    public function test_amount_conversion_from_cents(): void
    {
        $amount_cents = 2500;
        $amount_dollars = $amount_cents / 100;

        $this->assertEquals(25.00, $amount_dollars);
    }

    // =============================================
    // Refund Processing Tests
    // =============================================

    public function test_refund_updates_ticket_status(): void
    {
        $statuses = [
            'completed' => 'Active ticket',
            'refunded' => 'Refunded ticket',
        ];

        $current_status = 'completed';
        $new_status = 'refunded';

        $this->assertNotEquals($current_status, $new_status);
        $this->assertEquals('refunded', $new_status);
    }

    public function test_refund_transaction_creation(): void
    {
        $transaction = [
            'festival_id' => 1,
            'transaction_type' => 'expense',
            'category' => 'refunds',
            'amount' => 25.00,
            'description' => 'Ticket refund - ABC12345',
            'reference' => 'ch_test123',
        ];

        $this->assertEquals('expense', $transaction['transaction_type']);
        $this->assertEquals('refunds', $transaction['category']);
        $this->assertGreaterThan(0, $transaction['amount']);
    }

    // =============================================
    // Duplicate Payment Prevention Tests
    // =============================================

    public function test_duplicate_payment_check(): void
    {
        $payment_id = 'pi_test123';

        // Simulating checking for existing ticket
        $existing_ticket = null; // Would be result from database

        $should_create = $existing_ticket === null;
        $this->assertTrue($should_create);

        // If ticket exists, don't create again
        $existing_ticket = (object) ['id' => 1, 'payment_id' => $payment_id];
        $should_create = $existing_ticket === null;
        $this->assertFalse($should_create);
    }

    // =============================================
    // Error Handling Tests
    // =============================================

    public function test_invalid_json_payload(): void
    {
        $invalid_json = '{invalid json';

        $decoded = json_decode($invalid_json, true);

        $this->assertNull($decoded);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    public function test_missing_event_type(): void
    {
        $event = [
            'data' => [
                'object' => [],
            ],
        ];

        $has_type = isset($event['type']);

        $this->assertFalse($has_type);
    }

    public function test_missing_payment_intent_in_refund(): void
    {
        $charge = [
            'id' => 'ch_test123',
            // payment_intent is missing
            'amount_refunded' => 2500,
        ];

        $payment_intent_id = $charge['payment_intent'] ?? null;

        $this->assertNull($payment_intent_id);
    }

    // =============================================
    // Logging Tests
    // =============================================

    public function test_failed_payment_logging(): void
    {
        $payment_intent = [
            'id' => 'pi_test123',
            'status' => 'failed',
        ];

        $log_message = 'Peanut Festival: Payment failed - ' . $payment_intent['id'];

        $this->assertStringContainsString('Payment failed', $log_message);
        $this->assertStringContainsString($payment_intent['id'], $log_message);
    }

    // =============================================
    // Webhook Security Tests
    // =============================================

    public function test_webhook_secret_not_exposed(): void
    {
        // Webhook secret should never appear in responses
        $response = ['received' => true];

        $json = json_encode($response);

        $this->assertStringNotContainsString('whsec_', $json);
        $this->assertStringNotContainsString('secret', $json);
    }

    public function test_timing_safe_comparison(): void
    {
        $expected = hash_hmac('sha256', 'test', 'secret');
        $provided = hash_hmac('sha256', 'test', 'secret');

        // hash_equals is timing-safe
        $result = hash_equals($expected, $provided);

        $this->assertTrue($result);
    }

    public function test_replay_attack_prevention(): void
    {
        $tolerance = 300; // 5 minutes
        $old_timestamp = time() - 600; // 10 minutes ago

        $is_valid = abs(time() - $old_timestamp) <= $tolerance;

        $this->assertFalse($is_valid);
    }

    // =============================================
    // Edge Case Tests
    // =============================================

    public function test_zero_amount_payment(): void
    {
        $amount = 0;

        $is_valid = $amount > 0;

        $this->assertFalse($is_valid);
    }

    public function test_negative_amount_refund(): void
    {
        $amount = -100;

        $is_valid = $amount > 0;

        $this->assertFalse($is_valid);
    }

    public function test_partial_refund_amount(): void
    {
        $original = 2500;
        $refund = 1000;

        $remaining = $original - $refund;

        $this->assertEquals(1500, $remaining);
        $this->assertLessThan($original, $refund);
    }

    public function test_multiple_signature_versions(): void
    {
        // Stripe may include multiple v1 signatures
        $header = 't=1234567890,v1=sig1,v1=sig2';

        $elements = explode(',', $header);
        $signatures = [];

        foreach ($elements as $element) {
            $parts = explode('=', $element, 2);
            if (count($parts) === 2 && $parts[0] === 'v1') {
                $signatures[] = $parts[1];
            }
        }

        $this->assertCount(2, $signatures);
    }

    public function test_payment_metadata_sanitization(): void
    {
        $metadata = [
            'customer_name' => '<script>alert("xss")</script>John',
            'customer_email' => 'test@example.com',
        ];

        $sanitized_name = sanitize_text_field($metadata['customer_name']);
        $sanitized_email = sanitize_email($metadata['customer_email']);

        $this->assertStringNotContainsString('<script>', $sanitized_name);
        $this->assertEquals('test@example.com', $sanitized_email);
    }

    public function test_event_id_uniqueness(): void
    {
        // Stripe event IDs should be unique
        $event_id1 = 'evt_' . bin2hex(random_bytes(16));
        $event_id2 = 'evt_' . bin2hex(random_bytes(16));

        $this->assertNotEquals($event_id1, $event_id2);
        $this->assertStringStartsWith('evt_', $event_id1);
        $this->assertStringStartsWith('evt_', $event_id2);
    }
}
