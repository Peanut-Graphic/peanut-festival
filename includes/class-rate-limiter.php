<?php
/**
 * Rate Limiter for API endpoints
 *
 * Uses WordPress transients for simple rate limiting without external dependencies.
 *
 * @package Peanut_Festival
 */

if (!defined('ABSPATH')) {
    exit;
}

class Peanut_Festival_Rate_Limiter {

    /**
     * Default rate limits per endpoint type (requests per window)
     */
    private static array $limits = [
        'vote' => ['limit' => 10, 'window' => 60],           // 10 votes per minute
        'application' => ['limit' => 5, 'window' => 300],     // 5 applications per 5 minutes
        'payment' => ['limit' => 10, 'window' => 60],         // 10 payment attempts per minute
        'general' => ['limit' => 60, 'window' => 60],         // 60 requests per minute (for GET)
    ];

    /**
     * Check if the request should be rate limited
     *
     * @param string $action The action type (vote, application, payment, general)
     * @param string|null $identifier Optional identifier (defaults to IP)
     * @return array ['allowed' => bool, 'remaining' => int, 'reset' => int]
     */
    public static function check(string $action, ?string $identifier = null): array {
        $identifier = $identifier ?? self::get_identifier();
        $config = self::$limits[$action] ?? self::$limits['general'];

        $key = self::get_key($action, $identifier);
        $data = get_transient($key);

        $now = time();

        if ($data === false) {
            // First request - initialize
            $data = [
                'count' => 1,
                'window_start' => $now,
            ];
            set_transient($key, $data, $config['window']);

            return [
                'allowed' => true,
                'remaining' => $config['limit'] - 1,
                'reset' => $now + $config['window'],
            ];
        }

        // Check if window has expired (transient should handle this, but double-check)
        if ($now - $data['window_start'] >= $config['window']) {
            // New window
            $data = [
                'count' => 1,
                'window_start' => $now,
            ];
            set_transient($key, $data, $config['window']);

            return [
                'allowed' => true,
                'remaining' => $config['limit'] - 1,
                'reset' => $now + $config['window'],
            ];
        }

        // Within window - check limit
        if ($data['count'] >= $config['limit']) {
            $reset = $data['window_start'] + $config['window'];

            // Log the rate limit hit
            self::log_rate_limit($action, $identifier);

            return [
                'allowed' => false,
                'remaining' => 0,
                'reset' => $reset,
            ];
        }

        // Increment and allow
        $data['count']++;
        set_transient($key, $data, $config['window'] - ($now - $data['window_start']));

        return [
            'allowed' => true,
            'remaining' => $config['limit'] - $data['count'],
            'reset' => $data['window_start'] + $config['window'],
        ];
    }

    /**
     * Enforce rate limiting - returns WP_REST_Response if limited
     *
     * @param string $action The action type
     * @param string|null $identifier Optional identifier
     * @return \WP_REST_Response|null Null if allowed, WP_REST_Response if limited
     */
    public static function enforce(string $action, ?string $identifier = null): ?\WP_REST_Response {
        $result = self::check($action, $identifier);

        if (!$result['allowed']) {
            $response = new \WP_REST_Response([
                'success' => false,
                'code' => 'rate_limit_exceeded',
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $result['reset'] - time(),
            ], 429);

            $response->header('X-RateLimit-Limit', self::get_limit($action));
            $response->header('X-RateLimit-Remaining', $result['remaining']);
            $response->header('X-RateLimit-Reset', $result['reset']);
            $response->header('Retry-After', $result['reset'] - time());

            return $response;
        }

        return null;
    }

    /**
     * Get the limit for an action
     *
     * @param string $action The action type
     * @return int The limit
     */
    public static function get_limit(string $action): int {
        return self::$limits[$action]['limit'] ?? self::$limits['general']['limit'];
    }

    /**
     * Get the window for an action
     *
     * @param string $action The action type
     * @return int The window in seconds
     */
    public static function get_window(string $action): int {
        return self::$limits[$action]['window'] ?? self::$limits['general']['window'];
    }

    /**
     * Reset rate limit for an identifier
     *
     * @param string $action The action type
     * @param string|null $identifier Optional identifier
     */
    public static function reset(string $action, ?string $identifier = null): void {
        $identifier = $identifier ?? self::get_identifier();
        $key = self::get_key($action, $identifier);
        delete_transient($key);
    }

    /**
     * Get client identifier (hashed IP for privacy)
     *
     * @return string Hashed identifier
     */
    private static function get_identifier(): string {
        // Hash IP for privacy
        return wp_hash(self::get_client_ip() . wp_salt('auth'));
    }

    /**
     * Resolve the client IP address.
     *
     * X-Forwarded-For / X-Real-IP are client-controlled: any visitor can send
     * them. They are only believed when the TCP peer (REMOTE_ADDR) is a
     * configured trusted proxy, and even then X-Forwarded-For is walked from
     * the right (the hop appended by our own proxy) to the first address that
     * is not itself a trusted proxy. Left-most entries can be forged by the
     * client and are never trusted on their own.
     *
     * Trusted proxies come from the `trusted_proxies` setting (array or
     * comma/space separated list of IPs and CIDR ranges, IPv4 or IPv6) and the
     * `peanut_festival_trusted_proxies` filter. Default: none.
     *
     * @return string Client IP address (may be empty if REMOTE_ADDR is unusable).
     */
    public static function get_client_ip(): string {
        $remote = isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';
        $remote = filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '';

        $trusted = self::get_trusted_proxies();

        if ($remote === '' || empty($trusted) || !self::ip_in_ranges($remote, $trusted)) {
            return $remote;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $hops = array_reverse(array_map('trim', explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])));

            foreach ($hops as $hop) {
                if (!filter_var($hop, FILTER_VALIDATE_IP)) {
                    // A malformed hop means the chain cannot be trusted past here.
                    break;
                }
                if (!self::ip_in_ranges($hop, $trusted)) {
                    return $hop;
                }
            }

            return $remote;
        }

        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $real_ip = trim((string) $_SERVER['HTTP_X_REAL_IP']);
            if (filter_var($real_ip, FILTER_VALIDATE_IP)) {
                return $real_ip;
            }
        }

        return $remote;
    }

    /**
     * Configured trusted proxy IPs / CIDR ranges.
     *
     * @return string[]
     */
    public static function get_trusted_proxies(): array {
        $configured = Peanut_Festival_Settings::get('trusted_proxies', []);

        if (is_string($configured)) {
            $configured = preg_split('/[\s,]+/', $configured, -1, PREG_SPLIT_NO_EMPTY);
        }

        /**
         * Filter the proxies whose X-Forwarded-For / X-Real-IP headers are trusted.
         *
         * @param string[] $proxies IP addresses or CIDR ranges. Default empty.
         */
        $proxies = apply_filters('peanut_festival_trusted_proxies', is_array($configured) ? $configured : []);

        if (!is_array($proxies)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($proxy) {
            return is_string($proxy) ? trim($proxy) : '';
        }, $proxies)));
    }

    /**
     * Whether an IP address falls inside any of the given IPs / CIDR ranges.
     *
     * @param string   $ip     IPv4 or IPv6 address.
     * @param string[] $ranges Single addresses or CIDR ranges.
     */
    public static function ip_in_ranges(string $ip, array $ranges): bool {
        $ip_bin = @inet_pton($ip);
        if ($ip_bin === false) {
            return false;
        }

        foreach ($ranges as $range) {
            $range = trim((string) $range);
            if ($range === '') {
                continue;
            }

            $bits = null;
            if (strpos($range, '/') !== false) {
                [$range, $bits] = explode('/', $range, 2);
                if (!ctype_digit($bits)) {
                    continue;
                }
                $bits = (int) $bits;
            }

            $range_bin = @inet_pton($range);
            if ($range_bin === false || strlen($range_bin) !== strlen($ip_bin)) {
                continue;
            }

            $max_bits = strlen($ip_bin) * 8;
            if ($bits === null) {
                $bits = $max_bits;
            }
            if ($bits < 0 || $bits > $max_bits) {
                continue;
            }

            $full_bytes = intdiv($bits, 8);
            $remaining = $bits % 8;

            if (substr($ip_bin, 0, $full_bytes) !== substr($range_bin, 0, $full_bytes)) {
                continue;
            }

            if ($remaining === 0) {
                return true;
            }

            $mask = (0xFF << (8 - $remaining)) & 0xFF;
            if ((ord($ip_bin[$full_bytes]) & $mask) === (ord($range_bin[$full_bytes]) & $mask)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate transient key
     *
     * @param string $action The action type
     * @param string $identifier The identifier
     * @return string The transient key
     */
    private static function get_key(string $action, string $identifier): string {
        // Transient names are limited to 172 characters
        $hash = substr(md5($identifier), 0, 16);
        return 'pf_rl_' . $action . '_' . $hash;
    }

    /**
     * Log rate limit events
     *
     * @param string $action The action that was rate limited
     * @param string $identifier The identifier that was limited
     */
    private static function log_rate_limit(string $action, string $identifier): void {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'plugin' => 'peanut-festival',
            'event' => 'rate_limit_exceeded',
            'action' => $action,
            'identifier_hash' => substr($identifier, 0, 8) . '...',
        ];

        error_log('Peanut Festival Rate Limit: ' . wp_json_encode($log_entry));

        // Fire action for external monitoring
        do_action('peanut_festival_rate_limit', $action, $identifier);
    }

    /**
     * Add rate limit headers to a response
     *
     * @param \WP_REST_Response $response The response
     * @param string $action The action type
     * @param string|null $identifier Optional identifier
     * @return \WP_REST_Response The response with headers
     */
    public static function add_headers(\WP_REST_Response $response, string $action, ?string $identifier = null): \WP_REST_Response {
        $identifier = $identifier ?? self::get_identifier();
        $config = self::$limits[$action] ?? self::$limits['general'];
        $key = self::get_key($action, $identifier);
        $data = get_transient($key);

        $remaining = $config['limit'];
        $reset = time() + $config['window'];

        if ($data !== false) {
            $remaining = max(0, $config['limit'] - $data['count']);
            $reset = $data['window_start'] + $config['window'];
        }

        $response->header('X-RateLimit-Limit', $config['limit']);
        $response->header('X-RateLimit-Remaining', $remaining);
        $response->header('X-RateLimit-Reset', $reset);

        return $response;
    }
}
