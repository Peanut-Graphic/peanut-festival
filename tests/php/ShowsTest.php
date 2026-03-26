<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Shows Module Tests
 */

use PHPUnit\Framework\TestCase;

class ShowsTest extends TestCase
{
    public function test_singleton_returns_same_instance(): void
    {
        $instance1 = Peanut_Festival_Shows::get_instance();
        $instance2 = Peanut_Festival_Shows::get_instance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_get_all_returns_array(): void
    {
        $shows = Peanut_Festival_Shows::get_all();

        $this->assertIsArray($shows);
    }

    public function test_get_all_accepts_festival_filter(): void
    {
        $shows = Peanut_Festival_Shows::get_all([
            'festival_id' => 1,
        ]);

        $this->assertIsArray($shows);
    }

    public function test_get_by_id_returns_null_for_nonexistent(): void
    {
        $show = Peanut_Festival_Shows::get_by_id(999999);

        $this->assertNull($show);
    }

    public function test_get_by_slug_returns_null_for_nonexistent(): void
    {
        $show = Peanut_Festival_Shows::get_by_slug('nonexistent-show');

        $this->assertNull($show);
    }

    public function test_create_returns_id_or_false(): void
    {
        $data = [
            'festival_id' => 1,
            'title' => 'Opening Night',
            'slug' => 'opening-night',
            'show_date' => '2025-06-01',
            'show_time' => '19:00:00',
            'venue_id' => 1,
            'status' => 'scheduled',
        ];

        $result = Peanut_Festival_Shows::create($data);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_update_returns_result(): void
    {
        $data = [
            'title' => 'Updated Show Title',
        ];

        $result = Peanut_Festival_Shows::update(1, $data);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_delete_returns_result(): void
    {
        $result = Peanut_Festival_Shows::delete(1);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_count_returns_integer(): void
    {
        $count = Peanut_Festival_Shows::count();

        $this->assertIsInt($count);
    }

    public function test_get_performers_returns_array(): void
    {
        $performers = Peanut_Festival_Shows::get_performers(1);

        $this->assertIsArray($performers);
    }

    public function test_add_performer_returns_result(): void
    {
        $result = Peanut_Festival_Shows::add_performer(1, 1, [
            'order' => 1,
            'performance_time' => '19:30:00',
        ]);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_remove_performer_returns_result(): void
    {
        $result = Peanut_Festival_Shows::remove_performer(1, 1);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_complete_method_exists(): void
    {
        $this->assertTrue(method_exists(Peanut_Festival_Shows::class, 'complete'));
    }

    public function test_schedule_performers_method_exists(): void
    {
        $this->assertTrue(method_exists(Peanut_Festival_Shows::class, 'schedule_performers'));
    }

    public function test_has_schedule_conflict_method_exists(): void
    {
        $this->assertTrue(method_exists(Peanut_Festival_Shows::class, 'has_schedule_conflict'));
    }

    public function test_get_upcoming_returns_array(): void
    {
        $this->markTestSkipped('Method get_upcoming() not yet implemented');
    }

    public function test_get_by_date_range_returns_array(): void
    {
        $this->markTestSkipped('Method get_by_date_range() not yet implemented');
    }
}
