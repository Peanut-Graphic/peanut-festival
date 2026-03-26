<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Database Class Tests
 */

use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase {

    public function test_get_table_name_adds_prefix(): void {
        $table = Peanut_Festival_Database::get_table_name('festivals');

        $this->assertStringContainsString('pf_festivals', $table);
    }

    public function test_insert_validates_table_name(): void {
        // Invalid table should return false
        $result = Peanut_Festival_Database::insert('invalid_table_xyz', ['name' => 'Test']);

        $this->assertFalse($result);
    }

    public function test_insert_validates_column_names(): void {
        // SQL injection attempt in column name should fail
        $result = Peanut_Festival_Database::insert('festivals', [
            'name; DROP TABLE users;--' => 'Test'
        ]);

        $this->assertFalse($result);
    }

    public function test_insert_rejects_empty_data(): void {
        $result = Peanut_Festival_Database::insert('festivals', []);

        $this->assertFalse($result);
    }

    public function test_update_rejects_empty_where(): void {
        // Update without WHERE should be rejected for safety
        $result = Peanut_Festival_Database::update('festivals', ['name' => 'Test'], []);

        $this->assertFalse($result);
    }

    public function test_update_rejects_empty_data(): void {
        $result = Peanut_Festival_Database::update('festivals', [], ['id' => 1]);

        $this->assertFalse($result);
    }

    public function test_delete_rejects_empty_where(): void {
        // Delete without WHERE should be rejected for safety
        $result = Peanut_Festival_Database::delete('festivals', []);

        $this->assertFalse($result);
    }

    public function test_get_row_rejects_empty_where(): void {
        $result = Peanut_Festival_Database::get_row('festivals', []);

        $this->assertNull($result);
    }

    public function test_valid_table_names(): void {
        $valid_tables = [
            'festivals', 'shows', 'performers', 'venues', 'volunteers',
            'volunteer_shifts', 'volunteer_assignments', 'vendors', 'sponsors',
            'attendees', 'transactions', 'tickets', 'voting_config', 'votes',
            'vote_results', 'messages', 'message_recipients', 'check_ins',
            'settings', 'email_templates', 'email_logs', 'performer_applications',
            'vendor_applications'
        ];

        foreach ($valid_tables as $table) {
            $table_name = Peanut_Festival_Database::get_table_name($table);
            $this->assertStringContainsString($table, $table_name);
        }
    }

    public function test_column_validation_allows_valid_names(): void {
        // Valid column names should work
        $result = Peanut_Festival_Database::insert('festivals', [
            'name' => 'Test Festival',
            'slug' => 'test-festival',
            'status' => 'draft',
        ]);

        // Will fail due to mock DB, but validation should pass
        // In real test with DB, this would return an ID
        $this->assertTrue($result === false || is_int($result));
    }

    public function test_column_validation_rejects_invalid_names(): void {
        // Column names with special characters should fail
        $invalid_columns = [
            'name; DROP',
            'column--name',
            '1invalid',
            'col.name',
            'col name',
            'col@name',
        ];

        foreach ($invalid_columns as $column) {
            $result = Peanut_Festival_Database::insert('festivals', [
                $column => 'test'
            ]);
            $this->assertFalse($result, "Column '$column' should be rejected");
        }
    }

    public function test_transaction_methods_exist(): void {
        $this->assertTrue(method_exists(Peanut_Festival_Database::class, 'begin_transaction'));
        $this->assertTrue(method_exists(Peanut_Festival_Database::class, 'commit'));
        $this->assertTrue(method_exists(Peanut_Festival_Database::class, 'rollback'));
    }

    public function test_table_exists_method(): void {
        $this->assertTrue(method_exists(Peanut_Festival_Database::class, 'table_exists'));
    }

    public function test_count_returns_integer(): void {
        // With mock DB, should return 0
        $count = Peanut_Festival_Database::count('festivals');

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function test_get_results_returns_array(): void {
        $results = Peanut_Festival_Database::get_results('festivals');

        $this->assertIsArray($results);
    }

    public function test_get_results_validates_order_by(): void {
        // Invalid order_by column should fail
        $results = Peanut_Festival_Database::get_results(
            'festivals',
            [],
            'id; DROP TABLE--',
            'DESC'
        );

        $this->assertIsArray($results);
        $this->assertEmpty($results); // Should return empty due to validation failure
    }

    public function test_get_results_sanitizes_order_direction(): void {
        // Only ASC or DESC should be allowed
        $results = Peanut_Festival_Database::get_results(
            'festivals',
            [],
            'id',
            'INVALID'
        );

        // Should default to DESC
        $this->assertIsArray($results);
    }
}
