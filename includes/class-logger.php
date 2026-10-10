<?php
/**
 * Structured Logger for Peanut Festival
 *
 * Provides consistent, structured logging with context and log levels.
 *
 * @package Peanut_Festival
 */

if (!defined('ABSPATH')) {
    exit;
}

class Peanut_Festival_Logger {

    /**
     * Log levels (RFC 5424)
     */
    public const EMERGENCY = 'emergency';
    public const ALERT = 'alert';
    public const CRITICAL = 'critical';
    public const ERROR = 'error';
    public const WARNING = 'warning';
    public const NOTICE = 'notice';
    public const INFO = 'info';
    public const DEBUG = 'debug';

    /**
     * Log level priority (lower = more severe)
     */
    private const LEVEL_PRIORITY = [
        self::EMERGENCY => 0,
        self::ALERT => 1,
        self::CRITICAL => 2,
        self::ERROR => 3,
        self::WARNING => 4,
        self::NOTICE => 5,
        self::INFO => 6,
        self::DEBUG => 7,
    ];

    /**
     * Minimum log level to record
     */
    private static string $min_level = self::DEBUG;

    /**
     * Whether to log to database
     */
    private static bool $log_to_db = false;

    /**
     * Initialize logger settings
     */
    public static function init(): void {
        $settings = Peanut_Festival_Settings::get();
        self::$min_level = $settings['log_level'] ?? self::DEBUG;
        self::$log_to_db = !empty($settings['log_to_database']);
    }

    /**
     * Log a message with context
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public static function log(string $level, string $message, array $context = []): void {
        // Check if level should be logged
        if (!self::should_log($level)) {
            return;
        }

        $entry = self::build_entry($level, $message, $context);

        // Always log to error_log
        error_log(self::format_for_error_log($entry));

        // Optionally log to database
        if (self::$log_to_db) {
            self::log_to_database($entry);
        }

        // Fire action for external integrations
        do_action('peanut_festival_log', $entry);
    }

    /**
     * Check if a level should be logged based on minimum level
     *
     * @param string $level The log level to check
     * @return bool Whether to log this level
     */
    private static function should_log(string $level): bool {
        $level_priority = self::LEVEL_PRIORITY[$level] ?? 7;
        $min_priority = self::LEVEL_PRIORITY[self::$min_level] ?? 7;

        return $level_priority <= $min_priority;
    }

    /**
     * Build a structured log entry
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @return array The log entry
     */
    private static function build_entry(string $level, string $message, array $context): array {
        $entry = [
            'timestamp' => current_time('c'),
            'level' => $level,
            'message' => $message,
            'plugin' => 'peanut-festival',
            'version' => PEANUT_FESTIVAL_VERSION ?? '1.0.0',
        ];

        // Add request context
        if (!empty($_SERVER['REQUEST_URI'])) {
            $entry['request'] = [
                'uri' => sanitize_text_field($_SERVER['REQUEST_URI']),
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            ];
        }

        // Add user context if available
        if (function_exists('get_current_user_id') && get_current_user_id()) {
            $entry['user_id'] = get_current_user_id();
        }

        // Remove sensitive data from context
        $context = self::sanitize_context($context);

        if (!empty($context)) {
            $entry['context'] = $context;
        }

        // Add exception details if present
        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $exception = $context['exception'];
            $entry['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => self::format_trace($exception->getTrace()),
            ];
            unset($entry['context']['exception']);
        }

        return $entry;
    }

    /**
     * Remove sensitive data from context
     *
     * @param array $context The context array
     * @return array Sanitized context
     */
    private static function sanitize_context(array $context): array {
        $sensitive_keys = [
            'password', 'secret', 'api_key', 'apikey', 'token',
            'credit_card', 'card_number', 'cvv', 'ssn', 'authorization',
        ];

        foreach ($context as $key => $value) {
            $key_lower = strtolower($key);

            foreach ($sensitive_keys as $sensitive) {
                if (str_contains($key_lower, $sensitive)) {
                    $context[$key] = '[REDACTED]';
                    break;
                }
            }

            // Recursively sanitize nested arrays
            if (is_array($value)) {
                $context[$key] = self::sanitize_context($value);
            }
        }

        return $context;
    }

    /**
     * Format stack trace for logging
     *
     * @param array $trace The exception trace
     * @return array Formatted trace (limited to 10 frames)
     */
    private static function format_trace(array $trace): array {
        $formatted = [];

        foreach (array_slice($trace, 0, 10) as $frame) {
            $formatted[] = sprintf(
                '%s%s%s() at %s:%d',
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'] ?? 'unknown',
                $frame['file'] ?? 'unknown',
                $frame['line'] ?? 0
            );
        }

        return $formatted;
    }

    /**
     * Format entry for error_log
     *
     * @param array $entry The log entry
     * @return string Formatted string
     */
    private static function format_for_error_log(array $entry): string {
        $level = strtoupper($entry['level']);
        $message = $entry['message'];

        $parts = ["Peanut Festival [{$level}]", $message];

        if (!empty($entry['context'])) {
            $parts[] = wp_json_encode($entry['context']);
        }

        if (!empty($entry['exception'])) {
            $parts[] = sprintf(
                '%s: %s in %s:%d',
                $entry['exception']['class'],
                $entry['exception']['message'],
                $entry['exception']['file'],
                $entry['exception']['line']
            );
        }

        return implode(' | ', $parts);
    }

    /**
     * Log entry to database
     *
     * @param array $entry The log entry
     */
    private static function log_to_database(array $entry): void {
        global $wpdb;

        $table = $wpdb->prefix . 'pf_activity_log';

        $wpdb->insert($table, [
            'festival_id' => Peanut_Festival_Settings::get_active_festival_id(),
            'user_id' => $entry['user_id'] ?? null,
            'action' => 'log_' . $entry['level'],
            'entity_type' => 'system',
            'details' => wp_json_encode($entry),
            'ip_address' => self::get_client_ip(),
            'created_at' => current_time('mysql'),
        ]);
    }

    /**
     * Get client IP address
     *
     * @return string|null IP address
     */
    private static function get_client_ip(): ?string {
        // Forwarding headers are client-controlled; only believe them from a
        // configured trusted proxy (same resolution as the rate limiter).
        if (class_exists('Peanut_Festival_Rate_Limiter')) {
            $ip = Peanut_Festival_Rate_Limiter::get_client_ip();
            return $ip !== '' ? $ip : null;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    // Convenience methods for each log level

    public static function emergency(string $message, array $context = []): void {
        self::log(self::EMERGENCY, $message, $context);
    }

    public static function alert(string $message, array $context = []): void {
        self::log(self::ALERT, $message, $context);
    }

    public static function critical(string $message, array $context = []): void {
        self::log(self::CRITICAL, $message, $context);
    }

    public static function error(string $message, array $context = []): void {
        self::log(self::ERROR, $message, $context);
    }

    public static function warning(string $message, array $context = []): void {
        self::log(self::WARNING, $message, $context);
    }

    public static function notice(string $message, array $context = []): void {
        self::log(self::NOTICE, $message, $context);
    }

    public static function info(string $message, array $context = []): void {
        self::log(self::INFO, $message, $context);
    }

    public static function debug(string $message, array $context = []): void {
        self::log(self::DEBUG, $message, $context);
    }

    /**
     * Log an exception
     *
     * @param Throwable $exception The exception to log
     * @param string $message Optional message
     * @param array $context Additional context
     */
    public static function exception(Throwable $exception, string $message = '', array $context = []): void {
        $context['exception'] = $exception;
        $message = $message ?: $exception->getMessage();
        self::error($message, $context);
    }
}
