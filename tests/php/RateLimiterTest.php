<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Rate Limiter Tests
 */

use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        // Clear any existing transients
        global $transients;
        $transients = [];
    }

    public function test_first_request_is_allowed(): void {
        $result = Peanut_Festival_Rate_Limiter::check('vote');

        $this->assertTrue($result['allowed']);
        $this->assertEquals(9, $result['remaining']); // 10 - 1 = 9
    }

    public function test_requests_within_limit_are_allowed(): void {
        // Make 5 requests
        for ($i = 0; $i < 5; $i++) {
            $result = Peanut_Festival_Rate_Limiter::check('vote', 'test-user');
        }

        $this->assertTrue($result['allowed']);
        $this->assertEquals(5, $result['remaining']); // 10 - 5 = 5
    }

    public function test_requests_exceeding_limit_are_blocked(): void {
        // Make 11 requests (limit is 10)
        for ($i = 0; $i < 11; $i++) {
            $result = Peanut_Festival_Rate_Limiter::check('vote', 'test-user-2');
        }

        $this->assertFalse($result['allowed']);
        $this->assertEquals(0, $result['remaining']);
    }

    public function test_different_actions_have_separate_limits(): void {
        // Use up vote limit
        for ($i = 0; $i < 10; $i++) {
            Peanut_Festival_Rate_Limiter::check('vote', 'test-user-3');
        }

        // Application should still be allowed
        $result = Peanut_Festival_Rate_Limiter::check('application', 'test-user-3');

        $this->assertTrue($result['allowed']);
    }

    public function test_different_identifiers_have_separate_limits(): void {
        // Use up limit for user 1
        for ($i = 0; $i < 10; $i++) {
            Peanut_Festival_Rate_Limiter::check('vote', 'user-1');
        }

        // User 2 should still be allowed
        $result = Peanut_Festival_Rate_Limiter::check('vote', 'user-2');

        $this->assertTrue($result['allowed']);
    }

    public function test_reset_clears_limit(): void {
        // Use up limit
        for ($i = 0; $i < 10; $i++) {
            Peanut_Festival_Rate_Limiter::check('vote', 'reset-user');
        }

        // Verify blocked
        $result = Peanut_Festival_Rate_Limiter::check('vote', 'reset-user');
        $this->assertFalse($result['allowed']);

        // Reset
        Peanut_Festival_Rate_Limiter::reset('vote', 'reset-user');

        // Should be allowed again
        $result = Peanut_Festival_Rate_Limiter::check('vote', 'reset-user');
        $this->assertTrue($result['allowed']);
    }

    public function test_get_limit_returns_correct_value(): void {
        $this->assertEquals(10, Peanut_Festival_Rate_Limiter::get_limit('vote'));
        $this->assertEquals(5, Peanut_Festival_Rate_Limiter::get_limit('application'));
        $this->assertEquals(10, Peanut_Festival_Rate_Limiter::get_limit('payment'));
        $this->assertEquals(60, Peanut_Festival_Rate_Limiter::get_limit('general'));
    }

    public function test_get_window_returns_correct_value(): void {
        $this->assertEquals(60, Peanut_Festival_Rate_Limiter::get_window('vote'));
        $this->assertEquals(300, Peanut_Festival_Rate_Limiter::get_window('application'));
        $this->assertEquals(60, Peanut_Festival_Rate_Limiter::get_window('payment'));
    }

    public function test_unknown_action_uses_general_limits(): void {
        $limit = Peanut_Festival_Rate_Limiter::get_limit('unknown_action');
        $this->assertEquals(60, $limit);
    }
}
