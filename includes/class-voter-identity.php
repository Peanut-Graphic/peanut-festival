<?php
/**
 * Server-derived voter identity for public voting endpoints.
 *
 * Nothing a client sends in the request body decides "who" is voting. A voter
 * is identified by:
 *  - voter hash:  the logged-in user, otherwise a random id held in a
 *                 first-party cookie that the server issues and signs (HMAC);
 *                 a forged or tampered cookie is ignored and replaced;
 *  - client hash: keyed hash of the client IP (resolved by the rate limiter, so
 *                 forwarding headers are only trusted from configured proxies)
 *                 and User-Agent, so clearing cookies alone does not grant
 *                 another vote.
 *
 * Both are HMACs keyed with the site's auth salt, so no raw IP or cookie value
 * is stored.
 *
 * @package Peanut_Festival
 */

if (!defined('ABSPATH')) {
    exit;
}

class Peanut_Festival_Voter_Identity {

    /**
     * Cookie that carries the signed voter id.
     */
    public const COOKIE_NAME = 'pf_voter';

    /**
     * Cookie lifetime in seconds (30 days).
     */
    private const COOKIE_TTL = 2592000;

    /**
     * Build the signed cookie value for a voter id.
     *
     * @param string $id 32 lowercase hex characters.
     */
    public static function cookie_value_for(string $id): string {
        return $id . '.' . self::sign($id);
    }

    /**
     * Return the voter id from a cookie value, or null if it is missing,
     * malformed, or its signature does not verify.
     *
     * @param mixed $value Raw cookie value.
     */
    public static function read_cookie_id($value): ?string {
        if (!is_string($value) || !preg_match('/^([a-f0-9]{32})\.([a-f0-9]{64})$/', $value, $matches)) {
            return null;
        }

        return hash_equals(self::sign($matches[1]), $matches[2]) ? $matches[1] : null;
    }

    /**
     * Stable, server-derived identifier for the current voter.
     *
     * Issues (and signs) a voter cookie if the request does not carry a valid one.
     */
    public static function voter_hash(): string {
        $user_id = (int) get_current_user_id();
        $subject = $user_id > 0 ? 'user:' . $user_id : 'cookie:' . self::cookie_voter_id();

        return hash_hmac('sha256', 'voter|' . $subject, wp_salt('auth'));
    }

    /**
     * Keyed hash of the client network identity (IP + User-Agent).
     */
    public static function client_hash(): string {
        $ip = Peanut_Festival_Rate_Limiter::get_client_ip();
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 512) : '';

        return hash_hmac('sha256', 'client|' . $ip . '|' . $ua, wp_salt('auth'));
    }

    /**
     * Voter id from a valid signed cookie, or a freshly issued one.
     */
    private static function cookie_voter_id(): string {
        $existing = self::read_cookie_id($_COOKIE[self::COOKIE_NAME] ?? null);
        if ($existing !== null) {
            return $existing;
        }

        $id = bin2hex(random_bytes(16));
        self::issue_cookie($id);

        return $id;
    }

    /**
     * Send the signed voter cookie (first-party, HttpOnly, SameSite=Lax).
     */
    private static function issue_cookie(string $id): void {
        if (headers_sent()) {
            return;
        }

        setcookie(self::COOKIE_NAME, self::cookie_value_for($id), [
            'expires' => time() + self::COOKIE_TTL,
            'path' => (defined('COOKIEPATH') && COOKIEPATH) ? COOKIEPATH : '/',
            'domain' => (defined('COOKIE_DOMAIN') && COOKIE_DOMAIN) ? COOKIE_DOMAIN : '',
            'secure' => function_exists('is_ssl') && is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * HMAC signature for a voter id.
     */
    private static function sign(string $id): string {
        return hash_hmac('sha256', 'pf_voter|' . $id, wp_salt('auth'));
    }
}
