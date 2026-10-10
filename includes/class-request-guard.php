<?php
/**
 * Request guards shared by public, state-changing REST endpoints.
 *
 * @package Peanut_Festival
 */

if (!defined('ABSPATH')) {
    exit;
}

class Peanut_Festival_Request_Guard {

    /**
     * Require the request to come from this site's own pages.
     *
     * Browsers always attach Origin (and normally Referer) to POST requests, so
     * a same-site browser call passes; a cross-site form/fetch, or a script that
     * omits both headers, is refused. This is a CSRF/drive-by control, not
     * authentication: the voter identity and rate limits still apply.
     *
     * @param \WP_REST_Request $request The request.
     * @param string           $action  Short label for logging.
     * @return \WP_REST_Response|null 403 response when refused, null when allowed.
     */
    public static function require_same_origin(\WP_REST_Request $request, string $action): ?\WP_REST_Response {
        $origin = (string) $request->get_header('Origin');
        $referer = (string) $request->get_header('Referer');
        $source = $origin !== '' && $origin !== 'null' ? $origin : $referer;

        $host = $source !== '' ? wp_parse_url($source, PHP_URL_HOST) : null;
        $host = is_string($host) ? strtolower($host) : '';

        if ($host !== '' && in_array($host, self::allowed_hosts(), true)) {
            return null;
        }

        if (class_exists('Peanut_Festival_Logger')) {
            Peanut_Festival_Logger::warning('Cross-origin or origin-less request blocked', [
                'action' => $action,
                'origin' => $origin,
                'referer_host' => $host,
            ]);
        }

        return new \WP_REST_Response([
            'success' => false,
            'code' => 'cross_origin_blocked',
            'message' => 'Requests must come from this site.',
        ], 403);
    }

    /**
     * Hosts this site is served from.
     *
     * @return string[]
     */
    private static function allowed_hosts(): array {
        $hosts = [];
        foreach ([home_url(), site_url()] as $url) {
            $host = wp_parse_url($url, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $hosts[] = strtolower($host);
            }
        }

        /**
         * Filter the hosts allowed to submit public votes (e.g. a separate
         * front-end domain that embeds the voting widget).
         *
         * @param string[] $hosts Lower-case host names.
         */
        $hosts = apply_filters('peanut_festival_allowed_request_hosts', array_values(array_unique($hosts)));

        return array_map('strtolower', array_filter((array) $hosts, 'is_string'));
    }
}
