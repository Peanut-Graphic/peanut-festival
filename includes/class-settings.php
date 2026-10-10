<?php
/**
 * Settings management class
 *
 * Supports loading sensitive settings from environment variables.
 * Environment variables take precedence over database settings.
 *
 * Environment variable naming convention:
 *   Setting key: firebase_api_key
 *   Env var:     PEANUT_FESTIVAL_FIREBASE_API_KEY
 *
 * Environment overrides apply to single-key reads (Settings::get('key')) of
 * the keys in SENSITIVE_KEYS, e.g.:
 *   - PEANUT_FESTIVAL_FIREBASE_SERVICE_ACCOUNT (JSON string or base64-encoded)
 *   - PEANUT_FESTIVAL_STRIPE_WEBHOOK_SECRET
 *   - PEANUT_FESTIVAL_MAILCHIMP_API_KEY
 * Stripe API keys are read by Peanut_Festival_Payments from the
 * PEANUT_STRIPE_{TEST,LIVE}_{SECRET,PUBLISHABLE}_KEY constants/env first.
 *
 * REST exposure: secret keys (SECRET_KEYS) are never returned by the admin
 * settings API (masked as "••••" + last 4), and only users with
 * manage_options may change secrets or ADMIN_ONLY_KEYS.
 *
 * @package Peanut_Festival
 * @since   1.0.0
 * @since   1.3.0 Added environment variable support for sensitive settings.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Peanut_Festival_Settings {

    private const OPTION_KEY = 'peanut_festival_settings';

    /**
     * Prefix for environment variables.
     */
    private const ENV_PREFIX = 'PEANUT_FESTIVAL_';

    /**
     * Settings that support environment variable override.
     * These are sensitive credentials that should not be stored in the database.
     */
    private const SENSITIVE_KEYS = [
        'firebase_api_key',
        'firebase_project_id',
        'firebase_database_url',
        'firebase_service_account',
        'firebase_vapid_key',
        'stripe_test_secret_key',
        'stripe_test_publishable_key',
        'stripe_live_secret_key',
        'stripe_live_publishable_key',
        'stripe_webhook_secret',
        'eventbrite_token',
        'eventbrite_client_secret',
        'eventbrite_webhook_secret',
        'mailchimp_api_key',
        'booker_api_url',
        'booker_api_key',
        'ml_api_key',
    ];

    /**
     * Credentials stored in the settings option. Never returned by the REST
     * API in plaintext; writable only by users with manage_options.
     */
    private const SECRET_KEYS = [
        'stripe_test_secret_key',
        'stripe_live_secret_key',
        'stripe_webhook_secret',
        'firebase_service_account',
        'eventbrite_token',
        'eventbrite_client_secret',
        'eventbrite_webhook_secret',
        'mailchimp_api_key',
        'booker_api_key',
        'ml_api_key',
    ];

    /**
     * Secrets whose mask must not reveal a suffix (structured values).
     */
    private const NO_SUFFIX_SECRET_KEYS = [
        'firebase_service_account',
    ];

    /**
     * Not secret, but they decide where money, data or trust goes:
     * writable only by users with manage_options.
     */
    private const ADMIN_ONLY_KEYS = [
        'stripe_test_mode',
        'stripe_test_publishable_key',
        'stripe_live_publishable_key',
        'booker_api_url',
        'trusted_proxies',
    ];

    /**
     * Keys accepted by PUT /admin/settings and how each is sanitised.
     * Anything else in the request body is ignored.
     */
    private const REST_WRITABLE_KEYS = [
        'active_festival_id' => 'id',
        'notification_email' => 'email',
        'voting_weight_first' => 'int',
        'voting_weight_second' => 'int',
        'voting_weight_third' => 'int',
        'eventbrite_org_id' => 'text',
        'mailchimp_list_id' => 'text',
        'log_level' => 'log_level',
        'log_to_database' => 'bool',
        'ml_enabled' => 'bool',
        'ml_auto_train' => 'bool',
        // Administrator-only.
        'stripe_test_mode' => 'bool',
        'stripe_test_publishable_key' => 'text',
        'stripe_live_publishable_key' => 'text',
        'booker_api_url' => 'url',
        'trusted_proxies' => 'text',
        // Secrets (administrator-only).
        'stripe_test_secret_key' => 'secret',
        'stripe_live_secret_key' => 'secret',
        'stripe_webhook_secret' => 'secret',
        'firebase_service_account' => 'secret_json',
        'eventbrite_token' => 'secret',
        'eventbrite_client_secret' => 'secret',
        'eventbrite_webhook_secret' => 'secret',
        'mailchimp_api_key' => 'secret',
        'booker_api_key' => 'secret',
        'ml_api_key' => 'secret',
    ];

    /**
     * Prefix of a masked secret.
     */
    private const MASK = '••••';

    /**
     * Get a setting value.
     *
     * For sensitive settings, checks environment variables first.
     *
     * @since 1.0.0
     * @since 1.3.0 Added environment variable support.
     *
     * @param string $key Setting key. Empty to get all settings.
     * @param mixed $default Default value if not found.
     * @return mixed Setting value or all settings.
     */
    public static function get(string $key = '', mixed $default = null): mixed {
        // Return all settings (excluding env-only values for security)
        if (empty($key)) {
            return get_option(self::OPTION_KEY, []);
        }

        // Check environment variable first for sensitive keys
        if (self::is_sensitive($key)) {
            $env_value = self::get_from_env($key);
            if ($env_value !== null) {
                return $env_value;
            }
        }

        // Fall back to database
        $settings = get_option(self::OPTION_KEY, []);
        return $settings[$key] ?? $default;
    }

    /**
     * Get a setting from environment variable.
     *
     * @since 1.3.0
     *
     * @param string $key Setting key.
     * @return mixed|null Value from env or null if not set.
     */
    private static function get_from_env(string $key): mixed {
        $env_name = self::ENV_PREFIX . strtoupper($key);
        $value = getenv($env_name);

        // Also check $_ENV and $_SERVER for environments that don't populate getenv()
        if ($value === false) {
            $value = $_ENV[$env_name] ?? $_SERVER[$env_name] ?? false;
        }

        if ($value === false) {
            return null;
        }

        // Special handling for service account (may be base64 encoded)
        if ($key === 'firebase_service_account') {
            // Check if it's base64 encoded (doesn't start with '{')
            if (strpos($value, '{') !== 0) {
                $decoded = base64_decode($value, true);
                if ($decoded !== false && strpos($decoded, '{') === 0) {
                    $value = $decoded;
                }
            }
        }

        return $value;
    }

    /**
     * Check if a setting key is sensitive.
     *
     * @since 1.3.0
     *
     * @param string $key Setting key.
     * @return bool Whether the key is sensitive.
     */
    public static function is_sensitive(string $key): bool {
        return in_array($key, self::SENSITIVE_KEYS, true);
    }

    /**
     * Check if a sensitive setting is provided via environment variable.
     *
     * @since 1.3.0
     *
     * @param string $key Setting key.
     * @return bool Whether the setting is from an environment variable.
     */
    public static function is_from_env(string $key): bool {
        if (!self::is_sensitive($key)) {
            return false;
        }
        return self::get_from_env($key) !== null;
    }

    /**
     * Get the environment variable name for a setting.
     *
     * @since 1.3.0
     *
     * @param string $key Setting key.
     * @return string Environment variable name.
     */
    public static function get_env_name(string $key): string {
        return self::ENV_PREFIX . strtoupper($key);
    }

    /**
     * Set a setting value.
     *
     * Note: Setting values for keys that have environment variable overrides
     * will be stored but not used until the env var is removed.
     *
     * @since 1.0.0
     *
     * @param string $key Setting key.
     * @param mixed $value Setting value.
     * @return bool Success.
     */
    public static function set(string $key, mixed $value): bool {
        $settings = get_option(self::OPTION_KEY, []);
        $settings[$key] = $value;
        return update_option(self::OPTION_KEY, $settings);
    }

    /**
     * Update multiple settings at once.
     *
     * @since 1.0.0
     *
     * @param array $values Key-value pairs to update.
     * @return bool Success.
     */
    public static function update(array $values): bool {
        $settings = get_option(self::OPTION_KEY, []);
        $settings = array_merge($settings, $values);
        return update_option(self::OPTION_KEY, $settings);
    }

    /**
     * Delete a setting.
     *
     * @since 1.0.0
     *
     * @param string $key Setting key.
     * @return bool Success.
     */
    public static function delete(string $key): bool {
        $settings = get_option(self::OPTION_KEY, []);
        unset($settings[$key]);
        return update_option(self::OPTION_KEY, $settings);
    }

    /**
     * Get the active festival ID.
     *
     * @since 1.0.0
     *
     * @return int|null Festival ID or null.
     */
    public static function get_active_festival_id(): ?int {
        $id = self::get('active_festival_id');
        return $id ? (int) $id : null;
    }

    /**
     * Set the active festival ID.
     *
     * @since 1.0.0
     *
     * @param int|null $id Festival ID.
     * @return bool Success.
     */
    public static function set_active_festival_id(?int $id): bool {
        return self::set('active_festival_id', $id);
    }

    /**
     * Whether a key holds a credential that must never be returned in plaintext.
     */
    public static function is_secret(string $key): bool {
        return in_array($key, self::SECRET_KEYS, true);
    }

    /**
     * Mask a secret for display: "••••" plus the last four characters for
     * long values, "••••" alone otherwise, "" when empty.
     */
    public static function mask_secret(string $key, $value): string {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        if (strlen($value) < 16 || in_array($key, self::NO_SUFFIX_SECRET_KEYS, true)) {
            return self::MASK;
        }

        return self::MASK . substr($value, -4);
    }

    /**
     * All stored settings, safe to return over the REST API.
     *
     * Secrets are replaced by their mask, and `_secrets` reports for every
     * secret key whether a value is stored (`has_value`) and whether an
     * environment variable overrides it (`from_env`).
     *
     * @return array<string, mixed>
     */
    public static function get_for_rest(): array {
        $settings = (array) self::get();
        $meta = [];

        foreach (self::SECRET_KEYS as $key) {
            $stored = $settings[$key] ?? '';
            $has_value = is_string($stored) ? $stored !== '' : !empty($stored);

            if (array_key_exists($key, $settings)) {
                $settings[$key] = self::mask_secret($key, $stored);
            }

            $meta[$key] = [
                'has_value' => $has_value,
                'from_env' => self::is_from_env($key),
            ];
        }

        $settings['_secrets'] = $meta;

        return $settings;
    }

    /**
     * Apply a settings update from the REST API.
     *
     * Only REST_WRITABLE_KEYS are considered; values are sanitised per key. A
     * secret sent back as its own mask (or unchanged) is left alone, so the
     * settings form can round-trip. Changing a secret or an ADMIN_ONLY_KEYS
     * value without manage_options rejects the whole update.
     *
     * @param array $data                Decoded request body.
     * @param bool  $can_manage_secrets  Whether the user has manage_options.
     * @return array|WP_Error List of changed keys, or an error (403 / 400).
     */
    public static function apply_rest_update(array $data, bool $can_manage_secrets): array|WP_Error {
        $stored = (array) self::get();
        $changes = [];
        $denied = [];

        foreach ($data as $key => $raw) {
            if (!is_string($key) || !isset(self::REST_WRITABLE_KEYS[$key])) {
                continue;
            }

            $type = self::REST_WRITABLE_KEYS[$key];
            $current = $stored[$key] ?? null;
            $is_secret = self::is_secret($key);

            if ($is_secret) {
                if (!is_string($raw) && !is_numeric($raw) && $raw !== null) {
                    return new WP_Error('invalid_setting', sprintf('Invalid value for %s.', $key), ['status' => 400]);
                }
                $raw = (string) $raw;
                $current_string = is_string($current) || is_numeric($current) ? (string) $current : '';

                if ($raw === $current_string || ($current_string !== '' && $raw === self::mask_secret($key, $current_string))) {
                    continue; // Unchanged (possibly echoed back as its mask).
                }
            }

            $value = self::sanitize_rest_value($type, $raw);
            if (is_wp_error($value)) {
                return new WP_Error($value->get_error_code(), sprintf('Invalid value for %s.', $key), ['status' => 400]);
            }

            if (!$is_secret && self::values_equal($value, $current === null ? null : self::sanitize_rest_value($type, $current))) {
                continue;
            }

            if (!$can_manage_secrets && ($is_secret || in_array($key, self::ADMIN_ONLY_KEYS, true))) {
                $denied[] = $key;
                continue;
            }

            $changes[$key] = $value;
        }

        if (!empty($denied)) {
            return new WP_Error(
                'forbidden_setting',
                'Only administrators can change integration credentials and payment settings: ' . implode(', ', $denied),
                ['status' => 403, 'keys' => $denied]
            );
        }

        if (!empty($changes)) {
            self::update($changes);
        }

        return array_keys($changes);
    }

    /**
     * Sanitise one REST-supplied setting value.
     *
     * @param string $type  Sanitiser name from REST_WRITABLE_KEYS.
     * @param mixed  $value Raw value.
     * @return mixed|WP_Error
     */
    private static function sanitize_rest_value(string $type, $value) {
        switch ($type) {
            case 'id':
                $id = absint(is_scalar($value) ? $value : 0);
                return $id > 0 ? $id : null;

            case 'int':
                return is_scalar($value) ? (int) $value : 0;

            case 'bool':
                return is_string($value)
                    ? in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true)
                    : (bool) $value;

            case 'email':
                $raw = is_scalar($value) ? trim((string) $value) : '';
                if ($raw === '') {
                    return '';
                }
                $email = sanitize_email($raw);
                return is_email($email) ? $email : new WP_Error('invalid_email', 'Invalid email');

            case 'url':
                return is_scalar($value) ? esc_url_raw(trim((string) $value), ['https', 'http']) : '';

            case 'log_level':
                $levels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
                $level = is_scalar($value) ? strtolower(trim((string) $value)) : '';
                return in_array($level, $levels, true) ? $level : new WP_Error('invalid_setting', 'Invalid log level');

            case 'secret':
                return is_scalar($value) ? trim(wp_strip_all_tags((string) $value)) : '';

            case 'secret_json':
                $json = is_scalar($value) ? trim((string) $value) : '';
                if ($json === '') {
                    return '';
                }
                json_decode($json, true);
                return json_last_error() === JSON_ERROR_NONE ? $json : new WP_Error('invalid_setting', 'Invalid JSON');

            case 'text':
            default:
                return is_scalar($value) ? sanitize_text_field((string) $value) : '';
        }
    }

    /**
     * Loose-but-safe equality for comparing an incoming value with the stored one.
     *
     * @param mixed $a
     * @param mixed $b
     */
    private static function values_equal($a, $b): bool {
        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }
        if ($a === null || $b === null) {
            return $a === $b;
        }
        return (string) $a === (string) $b;
    }

    /**
     * Get all sensitive setting keys.
     *
     * @since 1.3.0
     *
     * @return array List of sensitive setting keys.
     */
    public static function get_sensitive_keys(): array {
        return self::SENSITIVE_KEYS;
    }

    /**
     * Get status of environment variable configuration.
     *
     * Useful for admin UI to show which settings are from env vars.
     *
     * @since 1.3.0
     *
     * @return array Associative array of key => bool (true if from env).
     */
    public static function get_env_status(): array {
        $status = [];
        foreach (self::SENSITIVE_KEYS as $key) {
            $status[$key] = self::is_from_env($key);
        }
        return $status;
    }
}
