<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Tests for input sanitization and validation
 */

use PHPUnit\Framework\TestCase;

class SanitizationTest extends TestCase
{
    public function testEmailSanitization(): void
    {
        // Valid email
        $email = 'test@example.com';
        $sanitized = sanitize_email($email);
        $this->assertEquals('test@example.com', $sanitized);

        // Email with spaces
        $email = '  test@example.com  ';
        $sanitized = sanitize_email(trim($email));
        $this->assertEquals('test@example.com', $sanitized);
    }

    public function testTextFieldSanitization(): void
    {
        // Remove HTML tags
        $input = '<script>alert("xss")</script>Normal text';
        $sanitized = sanitize_text_field($input);
        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringContainsString('Normal text', $sanitized);

        // Trim whitespace
        $input = '  Some text  ';
        $sanitized = sanitize_text_field($input);
        $this->assertEquals('Some text', $sanitized);
    }

    public function testSQLOrderByColumnValidation(): void
    {
        $allowedColumns = ['id', 'name', 'email', 'created_at', 'status'];

        // Valid column
        $orderBy = 'name';
        $isValid = in_array($orderBy, $allowedColumns, true);
        $this->assertTrue($isValid);

        // SQL injection attempt
        $orderBy = 'name; DROP TABLE users;';
        $isValid = in_array($orderBy, $allowedColumns, true);
        $this->assertFalse($isValid);

        // Case sensitivity
        $orderBy = 'NAME';
        $isValid = in_array($orderBy, $allowedColumns, true);
        $this->assertFalse($isValid, 'Column matching should be case-sensitive');
    }

    public function testSQLOrderDirectionValidation(): void
    {
        // Valid directions
        $this->assertEquals('ASC', strtoupper('asc') === 'ASC' ? 'ASC' : 'DESC');
        $this->assertEquals('DESC', strtoupper('desc') === 'ASC' ? 'ASC' : 'DESC');

        // Invalid defaults to DESC
        $direction = strtoupper('invalid');
        $result = $direction === 'ASC' ? 'ASC' : 'DESC';
        $this->assertEquals('DESC', $result);
    }

    public function testSlugValidation(): void
    {
        $validSlug = 'my-festival-2025';
        $isValid = preg_match('/^[a-z0-9-]+$/', $validSlug) === 1;
        $this->assertTrue($isValid);

        $invalidSlug = 'My Festival 2025';
        $isValid = preg_match('/^[a-z0-9-]+$/', $invalidSlug) === 1;
        $this->assertFalse($isValid);

        // Test slug generation
        $title = 'My Festival 2025';
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));
        $slug = trim($slug, '-');
        $this->assertEquals('my-festival-2025', $slug);
    }

    public function testDateValidation(): void
    {
        // Valid date format
        $date = '2025-06-15';
        $isValid = (bool) strtotime($date);
        $this->assertTrue($isValid);

        // Valid datetime
        $datetime = '2025-06-15 19:30:00';
        $isValid = (bool) strtotime($datetime);
        $this->assertTrue($isValid);

        // Invalid date
        $date = 'not-a-date';
        $isValid = (bool) strtotime($date);
        $this->assertFalse($isValid);
    }

    public function testPhoneNumberSanitization(): void
    {
        // Remove non-numeric characters
        $phone = '(555) 123-4567';
        $sanitized = preg_replace('/[^0-9+]/', '', $phone);
        $this->assertEquals('5551234567', $sanitized);

        // International format
        $phone = '+1-555-123-4567';
        $sanitized = preg_replace('/[^0-9+]/', '', $phone);
        $this->assertEquals('+15551234567', $sanitized);
    }

    public function testPriceSanitization(): void
    {
        // Float price
        $price = '25.99';
        $sanitized = (float) preg_replace('/[^0-9.]/', '', $price);
        $this->assertEquals(25.99, $sanitized);

        // With currency symbol
        $price = '$25.99';
        $sanitized = (float) preg_replace('/[^0-9.]/', '', $price);
        $this->assertEquals(25.99, $sanitized);

        // Negative should become 0
        $price = -10.00;
        $sanitized = max(0, $price);
        $this->assertEquals(0, $sanitized);
    }

    public function testURLSanitization(): void
    {
        // Valid URL
        $url = 'https://example.com/page?query=value';
        $sanitized = esc_url_raw($url);
        $this->assertStringStartsWith('https://', $sanitized);

        // Relative URL (might need protocol)
        $url = '//example.com/page';
        $isAbsolute = strpos($url, '://') !== false || strpos($url, '//') === 0;
        $this->assertTrue($isAbsolute);
    }

    public function testJSONValidation(): void
    {
        // Valid JSON
        $json = '{"name": "Test", "value": 123}';
        $decoded = json_decode($json);
        $this->assertNotNull($decoded);
        $this->assertEquals('Test', $decoded->name);

        // Invalid JSON
        $json = '{invalid json}';
        $decoded = json_decode($json);
        $this->assertNull($decoded);
    }

    public function testXSSPrevention(): void
    {
        $maliciousInputs = [
            '<script>alert("xss")</script>',
            'javascript:alert("xss")',
            '<img src=x onerror=alert("xss")>',
            '<svg onload=alert("xss")>',
            '"><script>alert("xss")</script>',
        ];

        foreach ($maliciousInputs as $input) {
            $sanitized = sanitize_text_field($input);
            $this->assertStringNotContainsString('<script>', $sanitized);
            $this->assertStringNotContainsString('onerror', $sanitized);
            $this->assertStringNotContainsString('onload', $sanitized);
        }
    }

    public function testCapacityValidation(): void
    {
        // Valid capacity
        $capacity = 100;
        $isValid = is_int($capacity) && $capacity > 0;
        $this->assertTrue($isValid);

        // Zero capacity (invalid)
        $capacity = 0;
        $isValid = is_int($capacity) && $capacity > 0;
        $this->assertFalse($isValid);

        // Negative capacity (invalid)
        $capacity = -50;
        $isValid = is_int($capacity) && $capacity > 0;
        $this->assertFalse($isValid);
    }
}
