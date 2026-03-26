<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * REST Response Helper Tests
 */

use PHPUnit\Framework\TestCase;

class RestResponseTest extends TestCase {

    public function test_success_response_structure(): void {
        $response = Peanut_Festival_REST_Response::success(['id' => 1], 'Created');

        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertEquals('Created', $data['message']);
        $this->assertEquals(['id' => 1], $data['data']);
        $this->assertEquals(200, $response->get_status());
    }

    public function test_success_without_message(): void {
        $response = Peanut_Festival_REST_Response::success(['test' => true]);

        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertArrayNotHasKey('message', $data);
        $this->assertEquals(['test' => true], $data['data']);
    }

    public function test_success_without_data(): void {
        $response = Peanut_Festival_REST_Response::success(null, 'Done');

        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertEquals('Done', $data['message']);
        $this->assertArrayNotHasKey('data', $data);
    }

    public function test_error_with_predefined_code(): void {
        $response = Peanut_Festival_REST_Response::error('not_found');

        $data = $response->get_data();

        $this->assertFalse($data['success']);
        $this->assertEquals('not_found', $data['code']);
        $this->assertEquals('Resource not found', $data['message']);
        $this->assertEquals(404, $response->get_status());
    }

    public function test_error_with_custom_message(): void {
        $response = Peanut_Festival_REST_Response::error('not_found', 'User not found');

        $data = $response->get_data();

        $this->assertEquals('User not found', $data['message']);
        $this->assertEquals(404, $response->get_status());
    }

    public function test_error_with_details(): void {
        $response = Peanut_Festival_REST_Response::error('validation_failed', null, [
            'email' => 'Invalid email format',
            'name' => 'Name is required',
        ]);

        $data = $response->get_data();

        $this->assertArrayHasKey('details', $data);
        $this->assertEquals('Invalid email format', $data['details']['email']);
    }

    public function test_paginated_response(): void {
        $items = [['id' => 1], ['id' => 2]];
        $response = Peanut_Festival_REST_Response::paginated($items, 50, 2, 10);

        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertEquals($items, $data['data']);
        $this->assertEquals(50, $data['pagination']['total']);
        $this->assertEquals(2, $data['pagination']['page']);
        $this->assertEquals(10, $data['pagination']['per_page']);
        $this->assertEquals(5, $data['pagination']['total_pages']);
        $this->assertTrue($data['pagination']['has_next']);
        $this->assertTrue($data['pagination']['has_prev']);
    }

    public function test_paginated_first_page(): void {
        $response = Peanut_Festival_REST_Response::paginated([], 100, 1, 20);

        $data = $response->get_data();

        $this->assertFalse($data['pagination']['has_prev']);
        $this->assertTrue($data['pagination']['has_next']);
    }

    public function test_paginated_last_page(): void {
        $response = Peanut_Festival_REST_Response::paginated([], 100, 5, 20);

        $data = $response->get_data();

        $this->assertTrue($data['pagination']['has_prev']);
        $this->assertFalse($data['pagination']['has_next']);
    }

    public function test_validation_error(): void {
        $errors = [
            'email' => 'Invalid email',
            'password' => 'Too short',
        ];

        $response = Peanut_Festival_REST_Response::validation_error($errors);

        $data = $response->get_data();

        $this->assertFalse($data['success']);
        $this->assertEquals('validation_failed', $data['code']);
        $this->assertEquals($errors, $data['errors']);
        $this->assertEquals(422, $response->get_status());
    }

    public function test_created_response(): void {
        $response = Peanut_Festival_REST_Response::created(['id' => 42]);

        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertEquals(201, $response->get_status());
        $this->assertEquals(['id' => 42], $data['data']);
    }

    public function test_no_content_response(): void {
        $response = Peanut_Festival_REST_Response::no_content();

        $this->assertEquals(204, $response->get_status());
        $this->assertNull($response->get_data());
    }

    public function test_accepted_response(): void {
        $response = Peanut_Festival_REST_Response::accepted('Processing started', ['job_id' => 'abc']);

        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertEquals(202, $response->get_status());
        $this->assertEquals('Processing started', $data['message']);
        $this->assertEquals(['job_id' => 'abc'], $data['data']);
    }

    public function test_error_codes_have_correct_status(): void {
        $codes = Peanut_Festival_REST_Response::get_error_codes();

        // Check a sampling of error codes
        $this->assertEquals(400, $codes['missing_parameters']['status']);
        $this->assertEquals(401, $codes['unauthorized']['status']);
        $this->assertEquals(403, $codes['forbidden']['status']);
        $this->assertEquals(404, $codes['not_found']['status']);
        $this->assertEquals(409, $codes['duplicate_entry']['status']);
        $this->assertEquals(422, $codes['validation_failed']['status']);
        $this->assertEquals(429, $codes['rate_limit_exceeded']['status']);
        $this->assertEquals(500, $codes['server_error']['status']);
        $this->assertEquals(503, $codes['service_unavailable']['status']);
    }

    public function test_unknown_error_code_uses_server_error(): void {
        $response = Peanut_Festival_REST_Response::error('unknown_code_xyz');

        $this->assertEquals(500, $response->get_status());
    }

    public function test_custom_error(): void {
        $response = Peanut_Festival_REST_Response::custom_error(
            'Something specific happened',
            418,
            'teapot_error',
            ['detail' => 'I am a teapot']
        );

        $data = $response->get_data();

        $this->assertFalse($data['success']);
        $this->assertEquals('Something specific happened', $data['message']);
        $this->assertEquals('teapot_error', $data['code']);
        $this->assertEquals(418, $response->get_status());
    }
}
