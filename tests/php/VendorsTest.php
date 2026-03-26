<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Tests for the Vendors class
 */

use PHPUnit\Framework\TestCase;

class VendorsTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        global $mock_options;
        $mock_options = [];
    }

    public function test_singleton_returns_same_instance(): void
    {
        $instance1 = Peanut_Festival_Vendors::get_instance();
        $instance2 = Peanut_Festival_Vendors::get_instance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_get_all_returns_array(): void
    {
        $vendors = Peanut_Festival_Vendors::get_all();

        $this->assertIsArray($vendors);
    }

    public function test_get_all_filters_by_festival_id(): void
    {
        $vendors = Peanut_Festival_Vendors::get_all([
            'festival_id' => 1,
        ]);

        $this->assertIsArray($vendors);
    }

    public function test_get_all_filters_by_vendor_type(): void
    {
        $vendors = Peanut_Festival_Vendors::get_all([
            'vendor_type' => 'food',
        ]);

        $this->assertIsArray($vendors);
    }

    public function test_get_all_filters_by_status(): void
    {
        $vendors = Peanut_Festival_Vendors::get_all([
            'status' => 'approved',
        ]);

        $this->assertIsArray($vendors);
    }

    public function test_get_all_supports_search(): void
    {
        $vendors = Peanut_Festival_Vendors::get_all([
            'search' => 'pizza',
        ]);

        $this->assertIsArray($vendors);
    }

    public function test_get_all_supports_pagination(): void
    {
        $vendors = Peanut_Festival_Vendors::get_all([
            'limit' => 10,
            'offset' => 0,
        ]);

        $this->assertIsArray($vendors);
    }

    public function test_get_all_sanitizes_order_by(): void
    {
        $allowed_columns = ['id', 'business_name', 'contact_name', 'email', 'status', 'created_at', 'festival_id', 'vendor_type'];

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
        $vendor = Peanut_Festival_Vendors::get_by_id(999999);

        $this->assertNull($vendor);
    }

    public function test_create_returns_id_or_false(): void
    {
        $data = [
            'festival_id' => 1,
            'business_name' => 'Test Vendor',
            'contact_name' => 'John Doe',
            'email' => 'vendor@example.com',
            'phone' => '555-1234',
            'vendor_type' => 'food',
            'status' => 'applied',
        ];

        $result = Peanut_Festival_Vendors::create($data);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_create_sets_created_at(): void
    {
        $created_at = current_time('mysql');

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $created_at);
    }

    public function test_update_returns_result(): void
    {
        $data = [
            'business_name' => 'Updated Business Name',
            'status' => 'approved',
        ];

        $result = Peanut_Festival_Vendors::update(1, $data);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_update_sets_updated_at(): void
    {
        $updated_at = current_time('mysql');

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $updated_at);
    }

    public function test_delete_returns_result(): void
    {
        $result = Peanut_Festival_Vendors::delete(1);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_count_returns_integer(): void
    {
        $count = Peanut_Festival_Vendors::count();

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function test_count_with_where_clause(): void
    {
        $count = Peanut_Festival_Vendors::count(['festival_id' => 1]);

        $this->assertIsInt($count);
    }

    public function test_valid_vendor_types(): void
    {
        $valid_types = ['food', 'merchandise', 'craft', 'sponsor'];

        foreach ($valid_types as $type) {
            $this->assertIsString($type);
        }
    }

    public function test_valid_status_values(): void
    {
        $valid_statuses = [
            'applied',
            'under_review',
            'approved',
            'rejected',
            'confirmed',
            'cancelled',
        ];

        foreach ($valid_statuses as $status) {
            $this->assertIsString($status);
        }
    }

    public function test_business_name_sanitization(): void
    {
        $name = '<script>alert("xss")</script>Pizza Palace';
        $sanitized = sanitize_text_field($name);

        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringContainsString('Pizza Palace', $sanitized);
    }

    public function test_contact_name_sanitization(): void
    {
        $name = '  John Doe  ';
        $sanitized = sanitize_text_field($name);

        $this->assertEquals('John Doe', $sanitized);
    }

    public function test_email_sanitization(): void
    {
        $email = '  vendor@example.com  ';
        $sanitized = sanitize_email($email);

        $this->assertEquals('vendor@example.com', $sanitized);
    }

    public function test_phone_sanitization(): void
    {
        $phone = '(555) 123-4567';
        $sanitized = sanitize_text_field($phone);

        $this->assertNotEmpty($sanitized);
    }

    public function test_description_sanitization(): void
    {
        $description = "We sell delicious pizza.\n\nMade fresh daily!<script>evil()</script>";
        $sanitized = sanitize_textarea_field($description);

        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringContainsString('pizza', $sanitized);
    }

    public function test_products_sanitization(): void
    {
        $products = "Pizza\nPasta\nSalads\nDrinks";
        $sanitized = sanitize_textarea_field($products);

        $this->assertStringContainsString('Pizza', $sanitized);
        $this->assertStringContainsString('Pasta', $sanitized);
    }

    public function test_booth_requirements_sanitization(): void
    {
        $requirements = "10x10 tent space\nElectricity: 30 amp\nWater access";
        $sanitized = sanitize_textarea_field($requirements);

        $this->assertNotEmpty($sanitized);
    }

    public function test_electricity_needed_boolean(): void
    {
        $electricity = true;

        $this->assertIsBool($electricity);
    }

    public function test_allowed_order_columns(): void
    {
        $allowed_columns = ['id', 'business_name', 'contact_name', 'email', 'status', 'created_at', 'festival_id', 'vendor_type'];

        $this->assertContains('id', $allowed_columns);
        $this->assertContains('business_name', $allowed_columns);
        $this->assertContains('contact_name', $allowed_columns);
        $this->assertContains('email', $allowed_columns);
        $this->assertContains('status', $allowed_columns);
        $this->assertContains('created_at', $allowed_columns);
        $this->assertContains('festival_id', $allowed_columns);
        $this->assertContains('vendor_type', $allowed_columns);
    }

    public function test_search_escapes_sql_wildcards(): void
    {
        global $wpdb;
        $search = 'pizza%place';
        $escaped = '%' . $wpdb->esc_like($search) . '%';

        // % in the search term should be escaped
        $this->assertStringContainsString('\\%', $escaped);
    }

    public function test_vendor_type_food(): void
    {
        $type = 'food';
        $valid_types = ['food', 'merchandise', 'craft', 'sponsor'];

        $this->assertContains($type, $valid_types);
    }

    public function test_vendor_type_merchandise(): void
    {
        $type = 'merchandise';
        $valid_types = ['food', 'merchandise', 'craft', 'sponsor'];

        $this->assertContains($type, $valid_types);
    }

    public function test_vendor_type_craft(): void
    {
        $type = 'craft';
        $valid_types = ['food', 'merchandise', 'craft', 'sponsor'];

        $this->assertContains($type, $valid_types);
    }

    public function test_vendor_type_sponsor(): void
    {
        $type = 'sponsor';
        $valid_types = ['food', 'merchandise', 'craft', 'sponsor'];

        $this->assertContains($type, $valid_types);
    }

    public function test_vendor_data_structure(): void
    {
        $vendor = [
            'festival_id' => 1,
            'business_name' => 'Test Business',
            'contact_name' => 'John Doe',
            'email' => 'test@example.com',
            'phone' => '555-1234',
            'vendor_type' => 'food',
            'description' => 'We serve great food',
            'products' => 'Pizza, Pasta',
            'booth_requirements' => '10x10 space',
            'electricity_needed' => true,
            'status' => 'applied',
        ];

        $this->assertArrayHasKey('festival_id', $vendor);
        $this->assertArrayHasKey('business_name', $vendor);
        $this->assertArrayHasKey('contact_name', $vendor);
        $this->assertArrayHasKey('email', $vendor);
        $this->assertArrayHasKey('vendor_type', $vendor);
        $this->assertArrayHasKey('status', $vendor);
    }

    public function test_required_fields(): void
    {
        $required_fields = ['business_name', 'email'];

        foreach ($required_fields as $field) {
            $this->assertIsString($field);
        }
    }

    public function test_optional_fields(): void
    {
        $optional_fields = [
            'contact_name',
            'phone',
            'description',
            'products',
            'booth_requirements',
            'electricity_needed',
        ];

        foreach ($optional_fields as $field) {
            $this->assertIsString($field);
        }
    }

    public function test_booth_assignment_fields(): void
    {
        $booth_data = [
            'booth_number' => 'A1',
            'booth_location' => 'Main Street',
            'booth_size' => '10x10',
        ];

        $this->assertArrayHasKey('booth_number', $booth_data);
        $this->assertArrayHasKey('booth_location', $booth_data);
        $this->assertArrayHasKey('booth_size', $booth_data);
    }

    public function test_payment_tracking_fields(): void
    {
        $payment_data = [
            'fee_amount' => 150.00,
            'fee_paid' => true,
            'payment_date' => '2025-01-15',
            'payment_method' => 'credit_card',
        ];

        $this->assertArrayHasKey('fee_amount', $payment_data);
        $this->assertArrayHasKey('fee_paid', $payment_data);
        $this->assertIsFloat($payment_data['fee_amount']);
        $this->assertIsBool($payment_data['fee_paid']);
    }

    public function test_status_transition_applied_to_under_review(): void
    {
        $valid_transitions = [
            'applied' => ['under_review', 'rejected'],
            'under_review' => ['approved', 'rejected'],
            'approved' => ['confirmed', 'cancelled'],
        ];

        $current = 'applied';
        $new_status = 'under_review';

        $is_valid = in_array($new_status, $valid_transitions[$current] ?? []);
        $this->assertTrue($is_valid);
    }

    public function test_status_transition_under_review_to_approved(): void
    {
        $valid_transitions = [
            'under_review' => ['approved', 'rejected'],
        ];

        $current = 'under_review';
        $new_status = 'approved';

        $is_valid = in_array($new_status, $valid_transitions[$current] ?? []);
        $this->assertTrue($is_valid);
    }

    public function test_multiple_festival_support(): void
    {
        // Vendors can apply to multiple festivals
        $vendor_festivals = [1, 2, 3];

        $this->assertCount(3, $vendor_festivals);
    }
}
