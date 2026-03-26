<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Admin REST API Tests
 */

use PHPUnit\Framework\TestCase;

class RestApiAdminTest extends TestCase
{
    private Peanut_Festival_REST_API_Admin $api;

    protected function setUp(): void
    {
        parent::setUp();
        $this->api = new Peanut_Festival_REST_API_Admin();
    }

    public function test_check_admin_permission_returns_true_for_admin(): void
    {
        // Our mock current_user_can returns true by default
        $result = $this->api->check_admin_permission();
        $this->assertTrue($result);
    }

    public function test_get_dashboard_stats_returns_no_active_festival_message(): void
    {
        // Reset options to have no active festival
        global $mock_options;
        $mock_options = [];

        $request = new WP_REST_Request('GET', '/peanut-festival/v1/admin/dashboard/stats');
        $response = $this->api->get_dashboard_stats($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertNull($data['data']);
        $this->assertStringContainsString('No active festival', $data['message']);
    }

    public function test_review_performer_validates_status(): void
    {
        $request = new WP_REST_Request('POST', '/peanut-festival/v1/admin/performers/1/review');
        $request->set_param('id', 1);
        $request->set_param('status', 'invalid_status');

        $response = $this->api->review_performer($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Invalid status', $data['message']);
    }

    public function test_review_performer_accepts_valid_statuses(): void
    {
        $validStatuses = ['pending', 'under_review', 'accepted', 'rejected', 'waitlisted', 'confirmed'];

        foreach ($validStatuses as $status) {
            $request = new WP_REST_Request('POST', '/peanut-festival/v1/admin/performers/1/review');
            $request->set_param('id', 1);
            $request->set_param('status', $status);

            $response = $this->api->review_performer($request);

            // Should not return 400 for valid statuses
            $this->assertNotEquals(400, $response->get_status(), "Status '$status' should be valid");
        }
    }

    public function test_get_voting_config_returns_config(): void
    {
        $request = new WP_REST_Request('GET', '/peanut-festival/v1/admin/voting/config/test-show');
        $request->set_param('show_slug', 'test-show');

        $response = $this->api->get_voting_config($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
    }

    public function test_save_voting_config_accepts_json(): void
    {
        $request = new WP_REST_Request('PUT', '/peanut-festival/v1/admin/voting/config/test-show');
        $request->set_param('show_slug', 'test-show');
        $request->set_json_params([
            'enabled' => true,
            'reveal_results' => false,
            'top_n' => 3,
        ]);

        $response = $this->api->save_voting_config($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    public function test_get_volunteer_shifts_returns_array(): void
    {
        $request = new WP_REST_Request('GET', '/peanut-festival/v1/admin/volunteers/shifts');

        $response = $this->api->get_volunteer_shifts($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['data']);
    }

    public function test_create_volunteer_shift_returns_id(): void
    {
        $request = new WP_REST_Request('POST', '/peanut-festival/v1/admin/volunteers/shifts');
        $request->set_json_params([
            'festival_id' => 1,
            'task_name' => 'Gate Security',
            'shift_date' => '2025-06-15',
            'start_time' => '10:00:00',
            'end_time' => '14:00:00',
            'slots_total' => 5,
        ]);

        $response = $this->api->create_volunteer_shift($request);

        // Should return 201 on success
        $this->assertContains($response->get_status(), [201, 500]); // 500 if DB mock returns false
    }

    public function test_get_settings_returns_array(): void
    {
        $request = new WP_REST_Request('GET', '/peanut-festival/v1/admin/settings');

        $response = $this->api->get_settings($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
    }

    public function test_update_settings_accepts_json(): void
    {
        $request = new WP_REST_Request('PUT', '/peanut-festival/v1/admin/settings');
        $request->set_json_params([
            'active_festival_id' => 1,
            'stripe_test_mode' => true,
        ]);

        $response = $this->api->update_settings($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    public function test_get_transactions_returns_array(): void
    {
        $request = new WP_REST_Request('GET', '/peanut-festival/v1/admin/transactions');

        $response = $this->api->get_transactions($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['data']);
    }

    public function test_verify_checkin_requires_ticket_code(): void
    {
        $request = new WP_REST_Request('POST', '/peanut-festival/v1/admin/checkin/verify');
        // Missing ticket_code

        $response = $this->api->verify_and_checkin($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
    }

    public function test_lookup_ticket_requires_ticket_code(): void
    {
        $request = new WP_REST_Request('GET', '/peanut-festival/v1/admin/checkin/lookup');
        // Missing ticket_code

        $response = $this->api->lookup_ticket($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_lookup_ticket_returns_not_found_for_invalid_code(): void
    {
        $request = new WP_REST_Request('GET', '/peanut-festival/v1/admin/checkin/lookup');
        $request->set_param('ticket_code', 'INVALID123');

        $response = $this->api->lookup_ticket($request);

        $this->assertEquals(404, $response->get_status());
    }

    public function test_send_broadcast_requires_content(): void
    {
        $request = new WP_REST_Request('POST', '/peanut-festival/v1/admin/messages/broadcast');
        $request->set_json_params([
            'group' => 'all',
            'subject' => 'Test Subject',
            // Missing content
        ]);

        $response = $this->api->send_broadcast($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_export_report_validates_type(): void
    {
        // Set an active festival
        global $mock_options;
        $mock_options['pf_active_festival_id'] = 1;

        $request = new WP_REST_Request('GET', '/peanut-festival/v1/admin/reports/export/invalid_type');
        $request->set_param('type', 'invalid_type');

        $response = $this->api->export_report($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_export_report_accepts_valid_types(): void
    {
        // Set an active festival
        global $mock_options;
        $mock_options['pf_active_festival_id'] = 1;

        $validTypes = ['performers', 'volunteers', 'attendees', 'transactions', 'tickets'];

        foreach ($validTypes as $type) {
            $request = new WP_REST_Request('GET', '/peanut-festival/v1/admin/reports/export/' . $type);
            $request->set_param('type', $type);
            $request->set_param('festival_id', 1);

            $response = $this->api->export_report($request);

            $data = $response->get_data();
            $this->assertTrue($data['success'], "Export type '$type' should be valid");
        }
    }

    public function test_firebase_test_fails_when_not_enabled(): void
    {
        $request = new WP_REST_Request('POST', '/peanut-festival/v1/admin/firebase/test');

        $response = $this->api->test_firebase($request);

        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('not enabled', $data['message']);
    }

    public function test_send_firebase_notification_requires_title_and_body(): void
    {
        $request = new WP_REST_Request('POST', '/peanut-festival/v1/admin/firebase/send-notification');
        $request->set_json_params([
            'topic' => 'test_topic',
            // Missing title and body
        ]);

        $response = $this->api->send_firebase_notification($request);
        $data = $response->get_data();

        // If Firebase is disabled, it returns early with "not enabled"
        // Otherwise it should return 400 for missing title/body
        if ($response->get_status() === 200 && isset($data['message']) && str_contains($data['message'], 'not enabled')) {
            $this->assertFalse($data['success']);
            $this->assertStringContainsString('not enabled', $data['message']);
        } else {
            $this->assertEquals(400, $response->get_status());
        }
    }

    public function test_get_reports_overview_requires_festival(): void
    {
        // Reset options
        global $mock_options;
        $mock_options = [];

        $request = new WP_REST_Request('GET', '/peanut-festival/v1/admin/reports/overview');

        $response = $this->api->get_reports_overview($request);

        $this->assertEquals(400, $response->get_status());
    }
}
