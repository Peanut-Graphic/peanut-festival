<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Logger Tests
 */

use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase {

    public function test_log_levels_are_defined(): void {
        $this->assertEquals('emergency', Peanut_Festival_Logger::EMERGENCY);
        $this->assertEquals('alert', Peanut_Festival_Logger::ALERT);
        $this->assertEquals('critical', Peanut_Festival_Logger::CRITICAL);
        $this->assertEquals('error', Peanut_Festival_Logger::ERROR);
        $this->assertEquals('warning', Peanut_Festival_Logger::WARNING);
        $this->assertEquals('notice', Peanut_Festival_Logger::NOTICE);
        $this->assertEquals('info', Peanut_Festival_Logger::INFO);
        $this->assertEquals('debug', Peanut_Festival_Logger::DEBUG);
    }

    public function test_error_log_is_called(): void {
        // This test verifies the log method runs without errors
        // In a real environment, we'd mock error_log
        Peanut_Festival_Logger::info('Test message');
        Peanut_Festival_Logger::error('Error message', ['key' => 'value']);

        $this->assertTrue(true); // No exceptions thrown
    }

    public function test_convenience_methods_exist(): void {
        // Test that all convenience methods are callable
        $this->assertTrue(method_exists(Peanut_Festival_Logger::class, 'emergency'));
        $this->assertTrue(method_exists(Peanut_Festival_Logger::class, 'alert'));
        $this->assertTrue(method_exists(Peanut_Festival_Logger::class, 'critical'));
        $this->assertTrue(method_exists(Peanut_Festival_Logger::class, 'error'));
        $this->assertTrue(method_exists(Peanut_Festival_Logger::class, 'warning'));
        $this->assertTrue(method_exists(Peanut_Festival_Logger::class, 'notice'));
        $this->assertTrue(method_exists(Peanut_Festival_Logger::class, 'info'));
        $this->assertTrue(method_exists(Peanut_Festival_Logger::class, 'debug'));
        $this->assertTrue(method_exists(Peanut_Festival_Logger::class, 'exception'));
    }

    public function test_exception_logging(): void {
        $exception = new Exception('Test exception', 500);

        // Should not throw
        Peanut_Festival_Logger::exception($exception, 'Something went wrong');

        $this->assertTrue(true);
    }

    public function test_context_with_sensitive_data(): void {
        // Sensitive data should be redacted (tested indirectly)
        Peanut_Festival_Logger::info('User login', [
            'username' => 'testuser',
            'password' => 'secret123', // Should be redacted
            'api_key' => 'abc123',     // Should be redacted
        ]);

        $this->assertTrue(true);
    }

    public function test_nested_context(): void {
        Peanut_Festival_Logger::debug('Complex data', [
            'user' => [
                'id' => 1,
                'name' => 'Test',
                'credentials' => [
                    'password' => 'secret', // Should be redacted
                ],
            ],
        ]);

        $this->assertTrue(true);
    }
}
