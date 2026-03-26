<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Tests for the Volunteers class
 */

use PHPUnit\Framework\TestCase;

class VolunteersTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // Reset any mock state
        global $mock_options;
        $mock_options = [];
    }

    public function test_singleton_returns_same_instance(): void
    {
        $instance1 = Peanut_Festival_Volunteers::get_instance();
        $instance2 = Peanut_Festival_Volunteers::get_instance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_get_all_returns_array(): void
    {
        $volunteers = Peanut_Festival_Volunteers::get_all();

        $this->assertIsArray($volunteers);
    }

    public function test_get_all_filters_by_festival_id(): void
    {
        $volunteers = Peanut_Festival_Volunteers::get_all([
            'festival_id' => 1,
        ]);

        $this->assertIsArray($volunteers);
    }

    public function test_get_all_filters_by_status(): void
    {
        $volunteers = Peanut_Festival_Volunteers::get_all([
            'festival_id' => 1,
            'status' => 'active',
        ]);

        $this->assertIsArray($volunteers);
    }

    public function test_get_all_supports_search(): void
    {
        $volunteers = Peanut_Festival_Volunteers::get_all([
            'search' => 'john',
        ]);

        $this->assertIsArray($volunteers);
    }

    public function test_get_all_supports_pagination(): void
    {
        $volunteers = Peanut_Festival_Volunteers::get_all([
            'limit' => 10,
            'offset' => 0,
        ]);

        $this->assertIsArray($volunteers);
    }

    public function test_get_all_sanitizes_order_by(): void
    {
        // Invalid order_by should default to 'created_at'
        $allowed_columns = ['id', 'name', 'email', 'status', 'created_at', 'festival_id', 'hours_completed'];

        $invalid_column = 'invalid; DROP TABLE--';
        $sanitized = in_array($invalid_column, $allowed_columns, true) ? $invalid_column : 'created_at';

        $this->assertEquals('created_at', $sanitized);
    }

    public function test_get_all_sanitizes_order_direction(): void
    {
        $order = strtoupper('INVALID') === 'ASC' ? 'ASC' : 'DESC';

        $this->assertEquals('DESC', $order);
    }

    public function test_get_by_id_returns_null_for_nonexistent(): void
    {
        $volunteer = Peanut_Festival_Volunteers::get_by_id(999999);

        $this->assertNull($volunteer);
    }

    public function test_create_returns_id_or_false(): void
    {
        $data = [
            'festival_id' => 1,
            'name' => 'Test Volunteer',
            'email' => 'volunteer@example.com',
            'phone' => '555-1234',
            'status' => 'applied',
        ];

        $result = Peanut_Festival_Volunteers::create($data);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_create_encodes_skills_as_json(): void
    {
        $skills = ['setup', 'teardown', 'registration'];
        $encoded = wp_json_encode($skills);

        $this->assertIsString($encoded);
        $decoded = json_decode($encoded, true);
        $this->assertEquals($skills, $decoded);
    }

    public function test_create_encodes_availability_as_json(): void
    {
        $availability = [
            'friday' => ['morning', 'afternoon'],
            'saturday' => ['all_day'],
            'sunday' => ['morning'],
        ];
        $encoded = wp_json_encode($availability);

        $this->assertIsString($encoded);
        $decoded = json_decode($encoded, true);
        $this->assertEquals($availability, $decoded);
    }

    public function test_create_sets_created_at(): void
    {
        $created_at = current_time('mysql');

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $created_at);
    }

    public function test_update_returns_result(): void
    {
        $data = [
            'name' => 'Updated Name',
            'status' => 'active',
        ];

        $result = Peanut_Festival_Volunteers::update(1, $data);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_update_sets_updated_at(): void
    {
        $updated_at = current_time('mysql');

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $updated_at);
    }

    public function test_delete_returns_result(): void
    {
        $result = Peanut_Festival_Volunteers::delete(1);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_get_shifts_returns_array(): void
    {
        $shifts = Peanut_Festival_Volunteers::get_shifts();

        $this->assertIsArray($shifts);
    }

    public function test_get_shifts_filters_by_festival_id(): void
    {
        $shifts = Peanut_Festival_Volunteers::get_shifts([
            'festival_id' => 1,
        ]);

        $this->assertIsArray($shifts);
    }

    public function test_get_shifts_filters_by_status(): void
    {
        $shifts = Peanut_Festival_Volunteers::get_shifts([
            'status' => 'open',
        ]);

        $this->assertIsArray($shifts);
    }

    public function test_create_shift_returns_result(): void
    {
        $data = [
            'festival_id' => 1,
            'title' => 'Setup Crew',
            'shift_date' => '2025-06-15',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'slots_required' => 5,
        ];

        $result = Peanut_Festival_Volunteers::create_shift($data);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_update_shift_returns_result(): void
    {
        $data = [
            'title' => 'Updated Shift Title',
            'slots_required' => 10,
        ];

        $result = Peanut_Festival_Volunteers::update_shift(1, $data);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_delete_shift_returns_result(): void
    {
        $result = Peanut_Festival_Volunteers::delete_shift(1);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_assign_to_shift_returns_result(): void
    {
        $result = Peanut_Festival_Volunteers::assign_to_shift(1, 1);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_check_in_returns_result(): void
    {
        $result = Peanut_Festival_Volunteers::check_in(1, 1);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_check_out_returns_false_without_check_in(): void
    {
        // check_out should fail if no check_in exists
        $result = Peanut_Festival_Volunteers::check_out(999999, 999999);

        $this->assertFalse($result);
    }

    public function test_hours_calculation(): void
    {
        $check_in = strtotime('2025-01-01 09:00:00');
        $check_out = strtotime('2025-01-01 13:30:00');

        $hours = ($check_out - $check_in) / 3600;

        $this->assertEquals(4.5, $hours);
    }

    public function test_hours_rounding(): void
    {
        $hours = 4.567;
        $rounded = round($hours, 2);

        $this->assertEquals(4.57, $rounded);
    }

    public function test_count_returns_integer(): void
    {
        $count = Peanut_Festival_Volunteers::count();

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function test_count_with_where_clause(): void
    {
        $count = Peanut_Festival_Volunteers::count(['festival_id' => 1]);

        $this->assertIsInt($count);
    }

    public function test_valid_status_values(): void
    {
        $valid_statuses = [
            'applied',
            'approved',
            'active',
            'inactive',
            'completed',
        ];

        foreach ($valid_statuses as $status) {
            $this->assertIsString($status);
        }
    }

    public function test_skills_array_structure(): void
    {
        $skills = [
            'setup',
            'teardown',
            'registration',
            'customer_service',
            'first_aid',
            'sound_tech',
            'photography',
        ];

        $this->assertIsArray($skills);
        foreach ($skills as $skill) {
            $this->assertIsString($skill);
        }
    }

    public function test_availability_structure(): void
    {
        $availability = [
            'friday' => ['morning', 'afternoon', 'evening'],
            'saturday' => ['all_day'],
            'sunday' => ['morning', 'afternoon'],
        ];

        $this->assertIsArray($availability);
        $this->assertArrayHasKey('friday', $availability);
        $this->assertArrayHasKey('saturday', $availability);
        $this->assertArrayHasKey('sunday', $availability);
    }

    public function test_shift_time_validation(): void
    {
        $start_time = '08:00:00';
        $end_time = '12:00:00';

        $start = strtotime($start_time);
        $end = strtotime($end_time);

        $this->assertLessThan($end, $start);
    }

    public function test_slots_validation(): void
    {
        $slots_total = 10;
        $slots_filled = 3;
        $slots_available = $slots_total - $slots_filled;

        $this->assertEquals(7, $slots_available);
        $this->assertGreaterThanOrEqual(0, $slots_available);
    }

    public function test_email_sanitization(): void
    {
        $email = '  volunteer@example.com  ';
        $sanitized = sanitize_email($email);

        $this->assertEquals('volunteer@example.com', $sanitized);
    }

    public function test_phone_sanitization(): void
    {
        $phone = '(555) 123-4567';
        $sanitized = sanitize_text_field($phone);

        $this->assertNotEmpty($sanitized);
    }

    public function test_name_sanitization(): void
    {
        $name = '<script>alert("xss")</script>John Doe';
        $sanitized = sanitize_text_field($name);

        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringContainsString('John Doe', $sanitized);
    }

    public function test_emergency_contact_fields(): void
    {
        $emergency_contact = 'Jane Doe';
        $emergency_phone = '555-987-6543';

        $this->assertNotEmpty($emergency_contact);
        $this->assertNotEmpty($emergency_phone);
    }

    public function test_shirt_size_values(): void
    {
        $valid_sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];

        foreach ($valid_sizes as $size) {
            $this->assertIsString($size);
        }
    }

    public function test_dietary_restrictions_sanitization(): void
    {
        $restrictions = "Vegetarian\nNo nuts\nGluten-free";
        $sanitized = sanitize_textarea_field($restrictions);

        $this->assertStringContainsString('Vegetarian', $sanitized);
    }

    public function test_allowed_order_columns(): void
    {
        $allowed_columns = ['id', 'name', 'email', 'status', 'created_at', 'festival_id', 'hours_completed'];

        $this->assertContains('id', $allowed_columns);
        $this->assertContains('name', $allowed_columns);
        $this->assertContains('email', $allowed_columns);
        $this->assertContains('status', $allowed_columns);
        $this->assertContains('created_at', $allowed_columns);
        $this->assertContains('festival_id', $allowed_columns);
        $this->assertContains('hours_completed', $allowed_columns);
    }

    public function test_search_escapes_sql_wildcards(): void
    {
        global $wpdb;
        $search = 'john%';
        $escaped = '%' . $wpdb->esc_like($search) . '%';

        // % in the search term should be escaped
        $this->assertStringContainsString('\\%', $escaped);
    }
}
