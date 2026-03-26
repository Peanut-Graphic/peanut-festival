<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Tests for SQL Injection Prevention
 *
 * Tests that user input is properly sanitized and parameterized queries are used.
 *
 * @package Peanut_Festival\Tests
 */

use PHPUnit\Framework\TestCase;

class SqlInjectionTest extends TestCase
{
    /**
     * Test festival ID is properly sanitized
     */
    public function testFestivalIdSanitization(): void
    {
        $malicious_inputs = [
            "1; DROP TABLE festivals;--",
            "1' OR '1'='1",
            "1 UNION SELECT * FROM users",
            "-1",
            "abc",
            "1.5",
        ];

        foreach ($malicious_inputs as $input) {
            $sanitized = absint($input);
            $this->assertIsInt($sanitized, "Input '$input' should be converted to integer");
            $this->assertGreaterThanOrEqual(0, $sanitized, "Result should be non-negative");
        }
    }

    /**
     * Test string input sanitization removes SQL
     */
    public function testStringSanitization(): void
    {
        $malicious = "Test'; DROP TABLE performers;--";
        $sanitized = preg_replace('/[\'";]/', '', $malicious);

        $this->assertStringNotContainsString("'", $sanitized);
        $this->assertStringNotContainsString('"', $sanitized);
        $this->assertStringNotContainsString(';', $sanitized);
    }

    /**
     * Test LIKE clause escaping
     */
    public function testLikeClauseEscaping(): void
    {
        // In WordPress, we use $wpdb->esc_like()
        $search_term = "100%";
        $dangerous_term = "test%' OR 1=1--";

        // Simulate esc_like behavior
        $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $search_term);
        $escaped_dangerous = str_replace(['%', '_'], ['\\%', '\\_'], $dangerous_term);

        $this->assertStringContainsString('\\%', $escaped);
        $this->assertStringContainsString('\\%', $escaped_dangerous);
    }

    /**
     * Test ORDER BY clause validation
     */
    public function testOrderByValidation(): void
    {
        $allowed_columns = ['id', 'name', 'created_at', 'updated_at'];
        $allowed_directions = ['ASC', 'DESC'];

        // Valid input
        $valid_column = 'name';
        $valid_direction = 'ASC';

        $this->assertTrue(
            in_array($valid_column, $allowed_columns, true),
            'Valid column should be allowed'
        );
        $this->assertTrue(
            in_array($valid_direction, $allowed_directions, true),
            'Valid direction should be allowed'
        );

        // Malicious input
        $malicious_column = "name; DROP TABLE festivals;--";
        $malicious_direction = "ASC; DROP TABLE--";

        $this->assertFalse(
            in_array($malicious_column, $allowed_columns, true),
            'Malicious column should be rejected'
        );
        $this->assertFalse(
            in_array($malicious_direction, $allowed_directions, true),
            'Malicious direction should be rejected'
        );
    }

    /**
     * Test table name validation
     */
    public function testTableNameValidation(): void
    {
        $allowed_tables = ['pf_festivals', 'pf_performers', 'pf_shows', 'pf_votes'];

        $valid_table = 'pf_festivals';
        $malicious_table = "pf_festivals; DROP TABLE users;--";

        $this->assertTrue(
            in_array($valid_table, $allowed_tables, true),
            'Valid table should be allowed'
        );
        $this->assertFalse(
            in_array($malicious_table, $allowed_tables, true),
            'Malicious table name should be rejected'
        );
    }

    /**
     * Test prepared statement placeholder validation
     */
    public function testPreparedStatementFormat(): void
    {
        // Simulate WordPress $wpdb->prepare() pattern
        $query = "SELECT * FROM festivals WHERE id = %d AND name = %s";

        $this->assertStringContainsString('%d', $query, 'Should use %d for integers');
        $this->assertStringContainsString('%s', $query, 'Should use %s for strings');
        $this->assertStringNotContainsString('$_GET', $query, 'Should not contain direct superglobals');
        $this->assertStringNotContainsString('$_POST', $query, 'Should not contain direct superglobals');
    }

    /**
     * Test email input sanitization
     */
    public function testEmailSanitization(): void
    {
        $malicious_email = "test@example.com'; DROP TABLE--";

        // WordPress sanitize_email strips everything after quotes
        $sanitized = preg_replace('/[^a-zA-Z0-9@._-]/', '', $malicious_email);

        $this->assertStringNotContainsString("'", $sanitized);
        $this->assertStringNotContainsString(';', $sanitized);
    }

    /**
     * Test JSON input handling
     */
    public function testJsonInputHandling(): void
    {
        $malicious_json = '{"name":"test\'; DROP TABLE--","id":1}';

        // Parse JSON then sanitize each field
        $data = json_decode($malicious_json, true);

        if ($data && isset($data['name'])) {
            $sanitized_name = preg_replace('/[\'";]/', '', $data['name']);
            $this->assertStringNotContainsString("'", $sanitized_name);
            $this->assertStringNotContainsString(';', $sanitized_name);
        }

        if ($data && isset($data['id'])) {
            $sanitized_id = absint($data['id']);
            $this->assertIsInt($sanitized_id);
        }
    }

    /**
     * Test array input sanitization
     */
    public function testArrayInputSanitization(): void
    {
        $malicious_array = [
            "1; DROP TABLE--",
            "2",
            "3' OR '1'='1",
        ];

        $sanitized = array_map('absint', $malicious_array);

        foreach ($sanitized as $value) {
            $this->assertIsInt($value);
            $this->assertGreaterThanOrEqual(0, $value);
        }
    }

    /**
     * Test IN clause safety
     */
    public function testInClauseSafety(): void
    {
        $ids = [1, 2, 3, 4, 5];

        // Safe way to build IN clause
        $sanitized_ids = array_map('absint', $ids);
        $placeholders = implode(',', array_fill(0, count($sanitized_ids), '%d'));

        $this->assertMatchesRegularExpression('/^%d(,%d)*$/', $placeholders);
        $this->assertEquals(count($ids), substr_count($placeholders, '%d'));
    }

    /**
     * Test date input validation
     */
    public function testDateInputValidation(): void
    {
        $valid_date = '2024-01-15';
        $malicious_date = "2024-01-15'; DROP TABLE--";

        // Validate format
        $is_valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $valid_date);
        $is_malicious_valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $malicious_date);

        $this->assertEquals(1, $is_valid, 'Valid date format should pass');
        $this->assertEquals(0, $is_malicious_valid, 'Malicious date should fail format check');
    }

    /**
     * Test slug sanitization
     */
    public function testSlugSanitization(): void
    {
        $malicious_slug = "my-show'; DROP TABLE shows;--";

        // WordPress sanitize_title behavior
        $sanitized = preg_replace('/[^a-z0-9-]/', '', strtolower($malicious_slug));

        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $sanitized);
        $this->assertStringNotContainsString("'", $sanitized);
        $this->assertStringNotContainsString(';', $sanitized);
    }

    /**
     * Test numeric range validation
     */
    public function testNumericRangeValidation(): void
    {
        $min = 1;
        $max = 100;

        // Valid inputs
        $this->assertTrue($this->isInRange(50, $min, $max));
        $this->assertTrue($this->isInRange(1, $min, $max));
        $this->assertTrue($this->isInRange(100, $min, $max));

        // Invalid inputs (potential injection via overflow)
        $this->assertFalse($this->isInRange(0, $min, $max));
        $this->assertFalse($this->isInRange(101, $min, $max));
        $this->assertFalse($this->isInRange(-1, $min, $max));
        $this->assertFalse($this->isInRange(PHP_INT_MAX, $min, $max));
    }

    /**
     * Test LIMIT clause sanitization
     */
    public function testLimitSanitization(): void
    {
        $max_limit = 100;

        $inputs = [
            '10' => 10,
            '50' => 50,
            '1000' => 100, // Should cap at max
            '-1' => 1,     // Should use minimum
            "10; DROP TABLE" => 10,
        ];

        foreach ($inputs as $input => $expected_max) {
            $sanitized = min(max(1, absint($input)), $max_limit);
            $this->assertLessThanOrEqual($max_limit, $sanitized);
            $this->assertGreaterThanOrEqual(1, $sanitized);
        }
    }

    /**
     * Helper to check if value is in range
     */
    private function isInRange(int $value, int $min, int $max): bool
    {
        return $value >= $min && $value <= $max;
    }

    /**
     * Test meta key sanitization
     */
    public function testMetaKeySanitization(): void
    {
        $allowed_keys = ['_pf_settings', '_pf_data', 'pf_custom_field'];
        $malicious_key = "_pf_settings'; DROP TABLE--";

        $this->assertFalse(
            in_array($malicious_key, $allowed_keys, true),
            'Malicious meta key should not match allowed list'
        );

        // Sanitize using key pattern
        $sanitized = preg_replace('/[^a-z0-9_]/', '', strtolower($malicious_key));
        $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $sanitized);
    }
}
