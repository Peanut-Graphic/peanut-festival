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
        $this->resetClientIpState();
    }

    protected function tearDown(): void {
        $this->resetClientIpState();
        parent::tearDown();
    }

    private function resetClientIpState(): void {
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_REAL_IP']);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        Peanut_Festival_Settings::delete('trusted_proxies');
    }

    // ------------------------------------------------------------------
    // Client IP resolution (spoofable forwarding headers)
    // ------------------------------------------------------------------

    public function test_client_ip_is_remote_addr_by_default(): void {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';
        $_SERVER['HTTP_X_REAL_IP'] = '198.51.100.2';

        $this->assertSame('203.0.113.9', Peanut_Festival_Rate_Limiter::get_client_ip());
    }

    public function test_spoofed_forwarded_for_cannot_reset_the_limit(): void {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';

        for ($i = 0; $i < 10; $i++) {
            $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.' . $i;
            $this->assertTrue(Peanut_Festival_Rate_Limiter::check('vote')['allowed']);
        }

        // An 11th request with yet another forged header is still the same client.
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.250';
        $this->assertFalse(Peanut_Festival_Rate_Limiter::check('vote')['allowed']);
    }

    public function test_spoofed_real_ip_cannot_reset_the_limit(): void {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';

        for ($i = 0; $i < 10; $i++) {
            $_SERVER['HTTP_X_REAL_IP'] = '192.0.2.' . $i;
            Peanut_Festival_Rate_Limiter::check('vote');
        }

        $_SERVER['HTTP_X_REAL_IP'] = '192.0.2.200';
        $this->assertFalse(Peanut_Festival_Rate_Limiter::check('vote')['allowed']);
    }

    public function test_forwarded_for_is_honoured_from_a_trusted_proxy(): void {
        Peanut_Festival_Settings::set('trusted_proxies', '10.0.0.0/8');
        $_SERVER['REMOTE_ADDR'] = '10.1.2.3';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';

        $this->assertSame('198.51.100.7', Peanut_Festival_Rate_Limiter::get_client_ip());
    }

    public function test_trusted_proxy_uses_rightmost_untrusted_hop_not_client_supplied_first_hop(): void {
        Peanut_Festival_Settings::set('trusted_proxies', ['10.0.0.0/8', '172.16.0.1']);
        $_SERVER['REMOTE_ADDR'] = '10.1.2.3';
        // The client forged "1.1.1.1"; the proxies appended the real address and themselves.
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1, 198.51.100.7, 172.16.0.1';

        $this->assertSame('198.51.100.7', Peanut_Festival_Rate_Limiter::get_client_ip());
    }

    public function test_forwarded_for_from_an_untrusted_peer_is_ignored_even_when_proxies_are_configured(): void {
        Peanut_Festival_Settings::set('trusted_proxies', '10.0.0.0/8');
        $_SERVER['REMOTE_ADDR'] = '203.0.113.50';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';

        $this->assertSame('203.0.113.50', Peanut_Festival_Rate_Limiter::get_client_ip());
    }

    public function test_real_ip_header_is_used_from_a_trusted_proxy_without_forwarded_for(): void {
        Peanut_Festival_Settings::set('trusted_proxies', '10.0.0.1');
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_REAL_IP'] = '198.51.100.8';

        $this->assertSame('198.51.100.8', Peanut_Festival_Rate_Limiter::get_client_ip());
    }

    public function test_garbage_forwarded_values_fall_back_to_remote_addr(): void {
        Peanut_Festival_Settings::set('trusted_proxies', '10.0.0.0/8');
        $_SERVER['REMOTE_ADDR'] = '10.0.0.9';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip, <script>';

        $this->assertSame('10.0.0.9', Peanut_Festival_Rate_Limiter::get_client_ip());
    }

    public function test_ipv6_cidr_trusted_proxy(): void {
        Peanut_Festival_Settings::set('trusted_proxies', '2001:db8::/32');
        $_SERVER['REMOTE_ADDR'] = '2001:db8::1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '2001:db9::42';

        $this->assertSame('2001:db9::42', Peanut_Festival_Rate_Limiter::get_client_ip());
    }

    public function test_ip_in_ranges(): void {
        $this->assertTrue(Peanut_Festival_Rate_Limiter::ip_in_ranges('10.20.30.40', ['10.0.0.0/8']));
        $this->assertFalse(Peanut_Festival_Rate_Limiter::ip_in_ranges('11.0.0.1', ['10.0.0.0/8']));
        $this->assertTrue(Peanut_Festival_Rate_Limiter::ip_in_ranges('192.0.2.1', ['192.0.2.1']));
        $this->assertFalse(Peanut_Festival_Rate_Limiter::ip_in_ranges('192.0.2.1', ['192.0.2.0/33']));
        $this->assertFalse(Peanut_Festival_Rate_Limiter::ip_in_ranges('192.0.2.1', ['2001:db8::/32']));
        $this->assertFalse(Peanut_Festival_Rate_Limiter::ip_in_ranges('192.0.2.1', []));
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
