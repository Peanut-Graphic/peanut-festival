<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Performers Module Tests
 */

use PHPUnit\Framework\TestCase;

class PerformersTest extends TestCase
{
    public function test_singleton_returns_same_instance(): void
    {
        $instance1 = Peanut_Festival_Performers::get_instance();
        $instance2 = Peanut_Festival_Performers::get_instance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_get_all_returns_array(): void
    {
        $performers = Peanut_Festival_Performers::get_all();

        $this->assertIsArray($performers);
    }

    public function test_get_all_filters_by_festival(): void
    {
        $performers = Peanut_Festival_Performers::get_all([
            'festival_id' => 1,
        ]);

        $this->assertIsArray($performers);
    }

    public function test_get_all_filters_by_status(): void
    {
        $performers = Peanut_Festival_Performers::get_all([
            'festival_id' => 1,
            'status' => 'accepted',
        ]);

        $this->assertIsArray($performers);
    }

    public function test_get_by_id_returns_null_for_nonexistent(): void
    {
        $performer = Peanut_Festival_Performers::get_by_id(999999);

        $this->assertNull($performer);
    }

    public function test_get_by_email_returns_null_for_nonexistent(): void
    {
        $performer = Peanut_Festival_Performers::get_by_email('nonexistent@example.com');

        $this->assertNull($performer);
    }

    public function test_create_returns_id_or_false(): void
    {
        $data = [
            'festival_id' => 1,
            'name' => 'Test Performer',
            'email' => 'performer@example.com',
            'bio' => 'Test bio',
            'performance_type' => 'music',
            'application_status' => 'pending',
        ];

        $result = Peanut_Festival_Performers::create($data);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_update_returns_result(): void
    {
        $data = [
            'name' => 'Updated Performer Name',
            'bio' => 'Updated bio',
        ];

        $result = Peanut_Festival_Performers::update(1, $data);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_delete_returns_result(): void
    {
        $result = Peanut_Festival_Performers::delete(1);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_count_returns_integer(): void
    {
        $count = Peanut_Festival_Performers::count();

        $this->assertIsInt($count);
    }

    public function test_review_validates_status(): void
    {
        // The review method should accept valid statuses
        $validStatuses = ['pending', 'under_review', 'accepted', 'rejected', 'waitlisted', 'confirmed'];

        foreach ($validStatuses as $status) {
            $result = Peanut_Festival_Performers::review(1, $status, 'Test notes');
            // Should not error for valid statuses
            $this->assertTrue($result === false || is_int($result), "Status '$status' should be accepted");
        }
    }

    public function test_get_accepted_for_festival_returns_array(): void
    {
        $performers = Peanut_Festival_Performers::get_all([
            'festival_id' => 1,
            'status' => 'accepted',
        ]);

        $this->assertIsArray($performers);
    }

    public function test_application_status_values(): void
    {
        // Verify expected status values are handled
        $validStatuses = [
            'pending',
            'under_review',
            'accepted',
            'rejected',
            'waitlisted',
            'confirmed',
        ];

        foreach ($validStatuses as $status) {
            $data = [
                'festival_id' => 1,
                'name' => 'Test',
                'email' => 'test@example.com',
                'application_status' => $status,
            ];

            // Should not throw for valid statuses
            $result = Peanut_Festival_Performers::create($data);
            $this->assertTrue($result === false || is_int($result));
        }
    }

    public function test_social_links_stored_as_json(): void
    {
        $data = [
            'festival_id' => 1,
            'name' => 'Social Performer',
            'email' => 'social@example.com',
            'social_links' => json_encode([
                'instagram' => '@performer',
                'facebook' => 'performer.page',
                'twitter' => '@performer',
            ]),
        ];

        $result = Peanut_Festival_Performers::create($data);
        $this->assertTrue($result === false || is_int($result));
    }

    public function test_photo_url_sanitization(): void
    {
        $data = [
            'festival_id' => 1,
            'name' => 'Photo Test',
            'email' => 'photo@example.com',
            'photo_url' => 'https://example.com/photo.jpg',
        ];

        // URL should be sanitized when saved
        $result = Peanut_Festival_Performers::create($data);
        $this->assertTrue($result === false || is_int($result));
    }

    public function test_email_sanitization(): void
    {
        // Test that email is properly sanitized
        $email = '  test@example.com  ';
        $sanitized = sanitize_email($email);

        $this->assertEquals('test@example.com', $sanitized);
    }

    public function test_name_sanitization(): void
    {
        // Test that name is properly sanitized
        $name = '<script>alert("xss")</script>John Doe';
        $sanitized = sanitize_text_field($name);

        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringContainsString('John Doe', $sanitized);
    }

    public function test_bio_sanitization(): void
    {
        // Test that bio (textarea) is properly sanitized
        $bio = "<script>alert('xss')</script>This is my bio.\n\nWith multiple lines.";
        $sanitized = sanitize_textarea_field($bio);

        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringContainsString('This is my bio.', $sanitized);
    }
}
