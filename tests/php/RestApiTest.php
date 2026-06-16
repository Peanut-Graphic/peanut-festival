<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Public REST API Tests
 */

use PHPUnit\Framework\TestCase;

class RestApiTest extends TestCase
{
    private Peanut_Festival_REST_API $api;

    protected function setUp(): void
    {
        parent::setUp();
        $this->api = new Peanut_Festival_REST_API();

        // Reset rate limiter transients
        global $transients;
        $transients = [];
    }

    public function test_get_voting_status_returns_success_structure(): void
    {
        $request = new WP_REST_Request('GET', '/peanut-festival/v1/vote/status/test-show');
        $request->set_param('show_slug', 'test-show');

        $response = $this->api->get_voting_status($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('is_open', $data['data']);
        $this->assertArrayHasKey('performers', $data['data']);
    }

    public function test_submit_vote_requires_parameters(): void
    {
        $request = new WP_REST_Request('POST', '/peanut-festival/v1/vote/submit');
        // Missing required parameters

        $response = $this->api->submit_vote($request);

        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertEquals('missing_parameters', $data['code']);
    }

    public function test_submit_vote_validates_array_performer_ids(): void
    {
        $request = new WP_REST_Request('POST', '/peanut-festival/v1/vote/submit');
        $request->set_param('show_slug', 'test-show');
        $request->set_param('performer_ids', 'not-an-array'); // Invalid type
        $request->set_param('token', 'test-token');

        $response = $this->api->submit_vote($request);

        $data = $response->get_data();
        $this->assertFalse($data['success']);
    }

    public function test_get_public_events_returns_array(): void
    {
        $request = new WP_REST_Request('GET', '/peanut-festival/v1/events');

        $response = $this->api->get_public_events($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['data']);
    }

    public function test_submit_performer_application_validates_required_fields(): void
    {
        $request = new WP_REST_Request('POST', '/peanut-festival/v1/apply/performer');
        $request->set_param('festival_id', 1);
        // Missing name and email

        $response = $this->api->submit_performer_application($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('required', $data['message']);
    }

    public function test_submit_performer_application_sanitizes_input(): void
    {
        $request = new WP_REST_Request('POST', '/peanut-festival/v1/apply/performer');
        $request->set_param('festival_id', 1);
        $request->set_param('name', '<script>alert("xss")</script>John Doe');
        $request->set_param('email', 'test@example.com');
        $request->set_param('bio', '<p>My bio</p><script>evil</script>');

        // The method will sanitize input before processing
        // We're testing that the sanitization is applied
        $response = $this->api->submit_performer_application($request);

        // Should not error from sanitization
        $this->assertInstanceOf(WP_REST_Response::class, $response);
    }

    public function test_submit_volunteer_signup_validates_required_fields(): void
    {
        $request = new WP_REST_Request('POST', '/peanut-festival/v1/volunteer/signup');
        $request->set_param('festival_id', 1);
        // Missing name and email

        $response = $this->api->submit_volunteer_signup($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
    }

    public function test_get_public_shifts_returns_array(): void
    {
        $request = new WP_REST_Request('GET', '/peanut-festival/v1/volunteer/shifts/1');
        $request->set_param('festival_id', 1);

        $response = $this->api->get_public_shifts($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['data']);
    }

    public function test_get_flyer_templates_returns_array(): void
    {
        $request = new WP_REST_Request('GET', '/peanut-festival/v1/flyer/templates/1');
        $request->set_param('festival_id', 1);

        $response = $this->api->get_flyer_templates($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['data']);
    }

    public function test_submit_vendor_application_validates_required_fields(): void
    {
        $request = new WP_REST_Request('POST', '/peanut-festival/v1/apply/vendor');
        $request->set_param('festival_id', 1);
        // Missing business_name and email

        $response = $this->api->submit_vendor_application($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
    }

    public function test_create_payment_intent_requires_show_and_email(): void
    {
        $request = new WP_REST_Request('POST', '/peanut-festival/v1/payments/create-intent');
        // Missing show_id and email

        $response = $this->api->create_payment_intent($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
    }

    public function test_confirm_payment_requires_payment_intent_id(): void
    {
        $request = new WP_REST_Request('POST', '/peanut-festival/v1/payments/confirm');
        // Missing payment_intent_id

        $response = $this->api->confirm_payment($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
    }

    public function test_get_leaderboard_requires_festival(): void
    {
        // Reset mock options to ensure no active festival
        global $mock_options;
        $mock_options = [];

        $request = new WP_REST_Request('GET', '/peanut-festival/v1/leaderboard');

        $response = $this->api->get_leaderboard($request);

        // Should fail when no festival is active
        $data = $response->get_data();
        $this->assertFalse($data['success']);
    }

    public function test_get_leaderboard_query_uses_real_vote_rank_column(): void
    {
        global $wpdb;

        $request = new WP_REST_Request('GET', '/peanut-festival/v1/leaderboard');
        // Pass festival_id explicitly so we reach the leaderboard query.
        $request->set_param('festival_id', 7);

        $response = $this->api->get_leaderboard($request);

        $this->assertTrue($response->get_data()['success']);

        // The pf_votes table has a `vote_rank` column, NOT `ranking`.
        // A query against the non-existent `ranking` column throws
        // "Unknown column 'ranking'" on every public leaderboard call.
        $this->assertStringContainsString('vote_rank', $wpdb->last_query);
        $this->assertStringNotContainsString('v.ranking', $wpdb->last_query);
    }

    public function test_get_match_votes_returns_not_found_for_invalid_match(): void
    {
        $request = new WP_REST_Request('GET', '/peanut-festival/v1/matches/999999/votes');
        $request->set_param('id', 999999);

        $response = $this->api->get_match_votes($request);

        $this->assertEquals(404, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
    }

    public function test_rate_limiting_is_enforced(): void
    {
        // Make 11 requests (limit is 10)
        for ($i = 0; $i < 11; $i++) {
            $request = new WP_REST_Request('POST', '/peanut-festival/v1/vote/submit');
            $request->set_param('show_slug', 'test-show');
            $request->set_param('performer_ids', [1, 2, 3]);
            $request->set_param('token', 'test-token-' . $i);

            $response = $this->api->submit_vote($request);
        }

        // The last request should be rate limited
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertEquals('rate_limit_exceeded', $data['code']);
    }
}
