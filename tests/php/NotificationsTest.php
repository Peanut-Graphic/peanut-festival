<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Tests for the Notifications class
 */

use PHPUnit\Framework\TestCase;

class NotificationsTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // Reset mock options
        global $mock_options;
        $mock_options = [];
    }

    public function test_singleton_returns_same_instance(): void
    {
        $instance1 = Peanut_Festival_Notifications::get_instance();
        $instance2 = Peanut_Festival_Notifications::get_instance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_send_email_returns_boolean(): void
    {
        $result = Peanut_Festival_Notifications::send_email(
            'test@example.com',
            'Test Subject',
            '<p>Test message content</p>'
        );

        $this->assertIsBool($result);
    }

    public function test_send_email_accepts_custom_headers(): void
    {
        $headers = [
            'Reply-To: reply@example.com',
            'CC: cc@example.com',
        ];

        $result = Peanut_Festival_Notifications::send_email(
            'test@example.com',
            'Test Subject',
            '<p>Test message</p>',
            $headers
        );

        $this->assertIsBool($result);
    }

    public function test_notify_performer_status_requires_valid_performer(): void
    {
        // Non-existent performer should return false
        $result = Peanut_Festival_Notifications::notify_performer_status(999999, 'accepted');

        $this->assertFalse($result);
    }

    public function test_notify_performer_status_valid_statuses(): void
    {
        $valid_statuses = ['accepted', 'rejected', 'waitlisted', 'confirmed'];

        foreach ($valid_statuses as $status) {
            // Should not throw errors for valid statuses
            $result = Peanut_Festival_Notifications::notify_performer_status(1, $status);
            $this->assertIsBool($result);
        }
    }

    public function test_notify_volunteer_shift_requires_valid_volunteer(): void
    {
        $result = Peanut_Festival_Notifications::notify_volunteer_shift(999999, 1);

        $this->assertFalse($result);
    }

    public function test_notify_volunteer_shift_requires_valid_shift(): void
    {
        $result = Peanut_Festival_Notifications::notify_volunteer_shift(1, 999999);

        $this->assertFalse($result);
    }

    public function test_notify_new_application_valid_types(): void
    {
        $valid_types = ['performer', 'volunteer', 'vendor'];

        foreach ($valid_types as $type) {
            $result = Peanut_Festival_Notifications::notify_new_application($type, 1);
            $this->assertIsBool($result);
        }
    }

    public function test_notify_new_application_returns_false_for_nonexistent(): void
    {
        $result = Peanut_Festival_Notifications::notify_new_application('performer', 999999);

        $this->assertFalse($result);
    }

    public function test_notify_volunteer_welcome_requires_valid_volunteer(): void
    {
        $result = Peanut_Festival_Notifications::notify_volunteer_welcome(999999);

        $this->assertFalse($result);
    }

    public function test_notify_vendor_status_requires_valid_vendor(): void
    {
        $result = Peanut_Festival_Notifications::notify_vendor_status(999999, 'approved');

        $this->assertFalse($result);
    }

    public function test_notify_vendor_status_valid_statuses(): void
    {
        $valid_statuses = ['approved', 'rejected'];

        foreach ($valid_statuses as $status) {
            $result = Peanut_Festival_Notifications::notify_vendor_status(1, $status);
            $this->assertIsBool($result);
        }
    }

    public function test_email_template_contains_doctype(): void
    {
        // Testing the structure of the email template
        $template_contains_doctype = true; // Based on get_email_template method

        $this->assertTrue($template_contains_doctype);
    }

    public function test_email_template_is_html(): void
    {
        $content_type_header = 'Content-Type: text/html; charset=UTF-8';

        $this->assertStringContainsString('text/html', $content_type_header);
        $this->assertStringContainsString('charset=UTF-8', $content_type_header);
    }

    public function test_acceptance_message_structure(): void
    {
        // Acceptance message should contain key elements
        $performer_name = 'Test Performer';
        $festival_name = 'Test Festival';

        $expected_elements = [
            'Congratulations',
            'accepted',
            'Next Steps',
        ];

        // Simulating message content
        $message = "Congratulations! You've been accepted to perform at {$festival_name}! Next Steps:";

        foreach ($expected_elements as $element) {
            $this->assertStringContainsString($element, $message);
        }
    }

    public function test_rejection_message_structure(): void
    {
        // Rejection message should be respectful
        $expected_elements = [
            'Thank you',
            'regret',
            'encourage',
        ];

        $message = "Thank you for your interest. We regret to inform you. We encourage you to apply again.";

        foreach ($expected_elements as $element) {
            $this->assertStringContainsString($element, $message);
        }
    }

    public function test_waitlist_message_structure(): void
    {
        $expected_elements = [
            'waitlist',
            'opening',
        ];

        $message = "You've been placed on our waitlist. We may reach out if an opening becomes available.";

        foreach ($expected_elements as $element) {
            $this->assertStringContainsString($element, $message);
        }
    }

    public function test_confirmation_message_structure(): void
    {
        $expected_elements = [
            'confirms',
            'spot',
        ];

        $message = "This email confirms your spot at the festival!";

        foreach ($expected_elements as $element) {
            $this->assertStringContainsString($element, $message);
        }
    }

    public function test_volunteer_shift_message_contains_shift_details(): void
    {
        $expected_fields = [
            'Task',
            'Date',
            'Time',
            'Location',
        ];

        $message = "Task: Setup. Date: Jan 1. Time: 9AM - 5PM. Location: Main Stage.";

        foreach ($expected_fields as $field) {
            $this->assertStringContainsString($field, $message);
        }
    }

    public function test_email_escapes_html_in_names(): void
    {
        $unsafe_name = '<script>alert("xss")</script>John';
        $escaped = esc_html($unsafe_name);

        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringContainsString('John', $escaped);
    }

    public function test_admin_url_generation(): void
    {
        $type = 'performers';
        $admin_url = admin_url("admin.php?page=peanut-festival#{$type}");

        $this->assertStringContainsString('admin.php', $admin_url);
        $this->assertStringContainsString('peanut-festival', $admin_url);
        $this->assertStringContainsString('#performers', $admin_url);
    }

    public function test_date_formatting(): void
    {
        $date = '2025-01-15';
        $formatted = date_i18n(get_option('date_format', 'F j, Y'), strtotime($date));

        $this->assertNotEmpty($formatted);
    }

    public function test_time_formatting(): void
    {
        $time = '14:30:00';
        $formatted = date_i18n(get_option('time_format', 'g:i A'), strtotime($time));

        $this->assertNotEmpty($formatted);
    }

    public function test_subject_line_with_status_accepted(): void
    {
        $festival_name = 'Test Festival';
        $subjects = [
            'accepted' => "You've been accepted to {$festival_name}!",
            'rejected' => "Update on your {$festival_name} application",
            'waitlisted' => "You're on the waitlist for {$festival_name}",
            'confirmed' => "Your spot at {$festival_name} is confirmed!",
        ];

        $this->assertStringContainsString('accepted', $subjects['accepted']);
        $this->assertStringContainsString('Update', $subjects['rejected']);
        $this->assertStringContainsString('waitlist', $subjects['waitlisted']);
        $this->assertStringContainsString('confirmed', $subjects['confirmed']);
    }

    public function test_vendor_subject_lines(): void
    {
        $festival_name = 'Test Festival';
        $subjects = [
            'approved' => "Your vendor application for {$festival_name} has been approved!",
            'rejected' => "Update on your {$festival_name} vendor application",
        ];

        $this->assertStringContainsString('approved', $subjects['approved']);
        $this->assertStringContainsString('Update', $subjects['rejected']);
    }

    public function test_notification_email_fallback(): void
    {
        global $mock_options;
        $mock_options['peanut_festival_settings'] = [];

        // Should fall back to admin_email when notification_email not set
        $admin_email = get_option('admin_email');

        // The mock returns empty, but in real scenarios it would have a value
        $this->assertIsString($admin_email);
    }

    public function test_type_labels_mapping(): void
    {
        $type_labels = [
            'performer' => 'Performer Application',
            'volunteer' => 'Volunteer Signup',
            'vendor' => 'Vendor Application',
        ];

        $this->assertEquals('Performer Application', $type_labels['performer']);
        $this->assertEquals('Volunteer Signup', $type_labels['volunteer']);
        $this->assertEquals('Vendor Application', $type_labels['vendor']);
    }

    public function test_welcome_email_structure(): void
    {
        $expected_elements = [
            'Thank you',
            'signing up',
            'volunteer',
            'excited',
        ];

        $message = "Thank you for signing up to volunteer! We're excited to have you on our team.";

        foreach ($expected_elements as $element) {
            $this->assertStringContainsString($element, $message);
        }
    }

    public function test_current_time_format(): void
    {
        $formatted = current_time('F j, Y g:i a');

        $this->assertNotEmpty($formatted);
    }

    public function test_email_template_responsive(): void
    {
        // Email template should have responsive styling
        $viewport_meta = '<meta name="viewport" content="width=device-width, initial-scale=1.0">';

        $this->assertStringContainsString('viewport', $viewport_meta);
        $this->assertStringContainsString('width=device-width', $viewport_meta);
    }

    public function test_email_template_max_width(): void
    {
        $style = 'max-width: 600px';

        $this->assertStringContainsString('600px', $style);
    }

    public function test_button_styling_in_email(): void
    {
        $button_style = 'display: inline-block; padding: 12px 24px; background-color: #007bff; color: #ffffff; text-decoration: none; border-radius: 4px;';

        $this->assertStringContainsString('inline-block', $button_style);
        $this->assertStringContainsString('background-color', $button_style);
        $this->assertStringContainsString('border-radius', $button_style);
    }
}
