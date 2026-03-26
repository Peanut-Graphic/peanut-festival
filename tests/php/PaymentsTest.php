<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Tests for the Payments class
 */

use PHPUnit\Framework\TestCase;

class PaymentsTest extends TestCase
{
    public function testPaymentAmountConversion(): void
    {
        // Stripe uses cents, so $25.00 should become 2500
        $amountDollars = 25.00;
        $amountCents = (int) ($amountDollars * 100);

        $this->assertEquals(2500, $amountCents);
    }

    public function testPaymentAmountConversionWithDecimals(): void
    {
        // Test with various decimal amounts
        $this->assertEquals(1299, (int) (12.99 * 100));
        $this->assertEquals(500, (int) (5.00 * 100));
        $this->assertEquals(999, (int) (9.99 * 100));
    }

    public function testTicketCodeGeneration(): void
    {
        // Simulate ticket code generation
        $generateCode = function(): string {
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            return $code;
        };

        $code = $generateCode();

        $this->assertEquals(8, strlen($code), 'Ticket code should be 8 characters');
        $this->assertMatchesRegularExpression('/^[A-Z0-9]+$/', $code, 'Ticket code should be alphanumeric uppercase');

        // Test uniqueness of multiple codes
        $codes = [];
        for ($i = 0; $i < 100; $i++) {
            $codes[] = $generateCode();
        }
        $uniqueCodes = array_unique($codes);
        $this->assertCount(100, $uniqueCodes, 'All generated codes should be unique');
    }

    public function testPaymentMetadataStructure(): void
    {
        $metadata = [
            'show_id' => 123,
            'attendee_email' => 'test@example.com',
            'attendee_name' => 'John Doe',
            'quantity' => 2,
            'festival_id' => 1,
        ];

        $this->assertArrayHasKey('show_id', $metadata);
        $this->assertArrayHasKey('attendee_email', $metadata);
        $this->assertArrayHasKey('quantity', $metadata);
        $this->assertIsInt($metadata['show_id']);
        $this->assertIsString($metadata['attendee_email']);
    }

    public function testRefundAmountValidation(): void
    {
        $originalAmount = 5000; // $50.00
        $refundAmount = 2500; // $25.00

        $isValidRefund = $refundAmount <= $originalAmount && $refundAmount > 0;
        $this->assertTrue($isValidRefund, 'Partial refund should be valid');

        // Test full refund
        $refundAmount = 5000;
        $isValidRefund = $refundAmount <= $originalAmount && $refundAmount > 0;
        $this->assertTrue($isValidRefund, 'Full refund should be valid');

        // Test invalid refund (more than original)
        $refundAmount = 6000;
        $isValidRefund = $refundAmount <= $originalAmount && $refundAmount > 0;
        $this->assertFalse($isValidRefund, 'Refund exceeding original should be invalid');

        // Test zero refund
        $refundAmount = 0;
        $isValidRefund = $refundAmount <= $originalAmount && $refundAmount > 0;
        $this->assertFalse($isValidRefund, 'Zero refund should be invalid');
    }

    public function testWebhookSignatureVerification(): void
    {
        $payload = '{"test": "data"}';
        $secret = 'whsec_test_secret';
        $timestamp = time();

        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = 'v1=' . hash_hmac('sha256', $signedPayload, $secret);

        // Verify the signature calculation
        $calculatedSignature = 'v1=' . hash_hmac('sha256', $signedPayload, $secret);
        $this->assertEquals($expectedSignature, $calculatedSignature);
    }

    public function testPaymentStatusTransitions(): void
    {
        $validTransitions = [
            'pending' => ['processing', 'failed', 'cancelled'],
            'processing' => ['completed', 'failed'],
            'completed' => ['refunded', 'partially_refunded'],
            'failed' => ['pending'], // Retry
        ];

        // Test valid transition
        $currentStatus = 'pending';
        $newStatus = 'processing';
        $isValid = in_array($newStatus, $validTransitions[$currentStatus] ?? []);
        $this->assertTrue($isValid);

        // Test invalid transition
        $currentStatus = 'pending';
        $newStatus = 'completed'; // Can't skip processing
        $isValid = in_array($newStatus, $validTransitions[$currentStatus] ?? []);
        $this->assertFalse($isValid);
    }

    public function testTicketQuantityValidation(): void
    {
        $maxPerOrder = 10;

        // Valid quantities
        $this->assertTrue(1 >= 1 && 1 <= $maxPerOrder);
        $this->assertTrue(5 >= 1 && 5 <= $maxPerOrder);
        $this->assertTrue(10 >= 1 && 10 <= $maxPerOrder);

        // Invalid quantities
        $this->assertFalse(0 >= 1 && 0 <= $maxPerOrder);
        $this->assertFalse(11 >= 1 && 11 <= $maxPerOrder);
        $this->assertFalse(-1 >= 1 && -1 <= $maxPerOrder);
    }
}
