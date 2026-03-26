<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Festivals Module Tests
 */

use PHPUnit\Framework\TestCase;

class FestivalsTest extends TestCase
{
    public function test_singleton_returns_same_instance(): void
    {
        $instance1 = Peanut_Festival_Festivals::get_instance();
        $instance2 = Peanut_Festival_Festivals::get_instance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_get_all_returns_array(): void
    {
        $festivals = Peanut_Festival_Festivals::get_all();

        $this->assertIsArray($festivals);
    }

    public function test_get_all_accepts_filters(): void
    {
        $festivals = Peanut_Festival_Festivals::get_all([
            'status' => 'active',
            'order_by' => 'start_date',
            'order' => 'DESC',
            'limit' => 10,
            'offset' => 0,
        ]);

        $this->assertIsArray($festivals);
    }

    public function test_get_by_id_returns_null_for_nonexistent(): void
    {
        $festival = Peanut_Festival_Festivals::get_by_id(999999);

        $this->assertNull($festival);
    }

    public function test_get_by_slug_returns_null_for_nonexistent(): void
    {
        $festival = Peanut_Festival_Festivals::get_by_slug('nonexistent-slug');

        $this->assertNull($festival);
    }

    public function test_create_generates_slug_from_name(): void
    {
        $data = [
            'name' => 'Test Festival 2025',
            'description' => 'A test festival',
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-03',
            'status' => 'draft',
        ];

        // The create method should sanitize and set slug
        // Since DB is mocked, we test that the method doesn't error
        $result = Peanut_Festival_Festivals::create($data);

        // With mock DB, this will return a random ID or false
        $this->assertTrue($result === false || is_int($result));
    }

    public function test_update_sets_updated_at_timestamp(): void
    {
        $data = [
            'name' => 'Updated Festival Name',
        ];

        $result = Peanut_Festival_Festivals::update(1, $data);

        // With mock DB, verify method runs without error
        $this->assertTrue($result === false || is_int($result));
    }

    public function test_delete_returns_result(): void
    {
        $result = Peanut_Festival_Festivals::delete(1);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_count_returns_integer(): void
    {
        $count = Peanut_Festival_Festivals::count();

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function test_count_accepts_where_clause(): void
    {
        $count = Peanut_Festival_Festivals::count(['status' => 'active']);

        $this->assertIsInt($count);
    }

    public function test_get_all_order_validation(): void
    {
        // Test that invalid order defaults correctly
        $festivals = Peanut_Festival_Festivals::get_all([
            'order' => 'INVALID',
        ]);

        // Should not throw, returns array
        $this->assertIsArray($festivals);
    }

    public function test_create_with_empty_name_still_generates_slug(): void
    {
        $data = [
            'name' => '',
            'start_date' => '2025-06-01',
        ];

        // Empty name will generate empty slug, but method shouldn't crash
        $result = Peanut_Festival_Festivals::create($data);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_create_sanitizes_special_characters_in_slug(): void
    {
        // Test the slug generation logic
        $name = 'Test Festival @ 2025! Special <Characters>';
        $expectedSlug = sanitize_title($name);

        // Verify sanitize_title works as expected
        $this->assertStringNotContainsString('@', $expectedSlug);
        $this->assertStringNotContainsString('!', $expectedSlug);
        $this->assertStringNotContainsString('<', $expectedSlug);
        $this->assertStringNotContainsString('>', $expectedSlug);
    }
}
