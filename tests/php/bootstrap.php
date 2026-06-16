<?php
/**
 * PHPUnit bootstrap file for Peanut Festival tests.
 *
 * NOTE: this file must NOT carry the `defined('ABSPATH') || exit;` web-access
 * guard that the plugin's include files use. That guard belongs in files that
 * could be hit directly over HTTP — but the TEST bootstrap is precisely the
 * thing that DEFINES ABSPATH below, so guarding here made the bootstrap exit
 * immediately and PHPUnit collect ZERO tests (a silent no-op suite). Removed.
 */

// Define test constants
define('ABSPATH', '/tmp/wordpress/');
define('WPINC', 'wp-includes');
define('PEANUT_FESTIVAL_VERSION', '1.0.0');
define('PEANUT_FESTIVAL_PATH', dirname(dirname(__DIR__)) . '/');
define('PEANUT_FESTIVAL_URL', 'http://example.com/wp-content/plugins/peanut-festival/');

// Mock WordPress functions used in the plugin
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'http://example.com/wp-content/plugins/' . basename(dirname($file)) . '/';
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return basename(dirname($file)) . '/' . basename($file);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        if ($type === 'mysql') {
            return date('Y-m-d H:i:s');
        }
        return time();
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 1;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return true;
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in() {
        return false;
    }
}

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs((int) $maybeint);
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $args = 1) {
        // No-op for testing
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $args = 1) {
        // No-op for testing
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value) {
        return $value;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook) {
        // No-op for testing
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback) {
        // No-op
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback) {
        // No-op
    }
}

if (!function_exists('get_option')) {
    $mock_options = [];
    function get_option($key, $default = false) {
        global $mock_options;
        if (array_key_exists($key, $mock_options)) {
            return $mock_options[$key];
        }
        // WordPress core always provides admin_email; mirror that so tests
        // exercising the admin-email fallback path see a real string.
        if ($key === 'admin_email') {
            return 'admin@example.com';
        }
        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($key, $value, $autoload = null) {
        global $mock_options;
        $mock_options[$key] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($key) {
        global $mock_options;
        unset($mock_options[$key]);
        return true;
    }
}

if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = []) {
        // Mock - just return true
        return true;
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = []) {
        return ['response' => ['code' => 200], 'body' => '{}'];
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = []) {
        return ['response' => ['code' => 200], 'body' => '{}'];
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return false;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return $response['body'] ?? '';
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return $response['response']['code'] ?? 200;
    }
}

if (!function_exists('wp_salt')) {
    function wp_salt($scheme = 'auth') {
        return 'test-salt-' . $scheme;
    }
}

if (!function_exists('wp_hash')) {
    function wp_hash($data, $scheme = 'auth') {
        return hash_hmac('sha256', $data, wp_salt($scheme));
    }
}

// Mock transients storage
$transients = [];

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0) {
        global $transients;
        $transients[$key] = $value;
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key) {
        global $transients;
        return $transients[$key] ?? false;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        global $transients;
        if (isset($transients[$key])) {
            unset($transients[$key]);
            return true;
        }
        return false;
    }
}

// Mock WP_REST_Response class
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        private $data;
        private $status;
        private $headers = [];

        public function __construct($data = null, $status = 200, $headers = []) {
            $this->data = $data;
            $this->status = $status;
            $this->headers = $headers;
        }

        public function get_data() {
            return $this->data;
        }

        public function get_status() {
            return $this->status;
        }

        public function set_status($status) {
            $this->status = $status;
        }

        public function get_headers() {
            return $this->headers;
        }

        public function header($name, $value) {
            $this->headers[$name] = $value;
        }
    }
}

// Mock WP_REST_Request class
if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private $params = [];
        private $json_params = [];
        private $method = 'GET';
        private $route = '';
        private $headers = [];

        public function __construct($method = 'GET', $route = '') {
            $this->method = $method;
            $this->route = $route;
        }

        public function set_header($key, $value) {
            $this->headers[strtolower($key)] = $value;
        }

        public function get_header($key) {
            return $this->headers[strtolower($key)] ?? null;
        }

        public function get_route() {
            return $this->route;
        }

        public function set_param($key, $value) {
            $this->params[$key] = $value;
        }

        public function get_param($key) {
            return $this->params[$key] ?? null;
        }

        public function get_params() {
            return $this->params;
        }

        public function set_json_params($params) {
            $this->json_params = $params;
        }

        public function get_json_params() {
            return $this->json_params;
        }

        public function get_method() {
            return $this->method;
        }
    }
}

// Mock wp_parse_args
if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = []) {
        if (is_object($args)) {
            $args = get_object_vars($args);
        } elseif (is_string($args)) {
            parse_str($args, $args);
        }
        return array_merge($defaults, $args);
    }
}

// Mock sanitize_title
if (!function_exists('sanitize_title')) {
    function sanitize_title($title) {
        $title = strtolower($title);
        $title = preg_replace('/[^a-z0-9-]/', '-', $title);
        $title = preg_replace('/-+/', '-', $title);
        return trim($title, '-');
    }
}

// Mock wp_kses_post
if (!function_exists('wp_kses_post')) {
    function wp_kses_post($string) {
        return strip_tags($string, '<a><p><br><strong><em><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><code><pre>');
    }
}

// Mock wp_upload_dir
if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        return [
            'basedir' => '/tmp/uploads',
            'baseurl' => 'http://example.com/wp-content/uploads',
            'path' => '/tmp/uploads/' . date('Y/m'),
            'url' => 'http://example.com/wp-content/uploads/' . date('Y/m'),
            'error' => false,
        ];
    }
}

// Mock wp_mkdir_p
if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target) {
        if (file_exists($target)) {
            return true;
        }
        return @mkdir($target, 0755, true);
    }
}

// Mock register_rest_route (for loading API classes)
if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args) {
        // No-op for testing
    }
}

// Mock rest_url
if (!function_exists('rest_url')) {
    function rest_url($path = '') {
        return 'http://example.com/wp-json/' . ltrim($path, '/');
    }
}

// Mock wp_remote_request
if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args = []) {
        return ['response' => ['code' => 200], 'body' => '{}'];
    }
}

// Mock home_url
if (!function_exists('home_url')) {
    function home_url($path = '') {
        return 'http://example.com' . $path;
    }
}

// Mock wp_parse_url
if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) {
        return parse_url($url, $component);
    }
}

// Mock get_site_url
if (!function_exists('get_site_url')) {
    function get_site_url($blog_id = null, $path = '', $scheme = null) {
        return 'http://example.com' . $path;
    }
}

// Mock site_url
if (!function_exists('site_url')) {
    function site_url($path = '', $scheme = null) {
        return 'http://example.com' . $path;
    }
}

// Mock admin_url
if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'http://example.com/wp-admin/' . ltrim($path, '/');
    }
}

// Mock get_bloginfo
if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '') {
        $info = [
            'name' => 'Test Site',
            'description' => 'Just another WordPress site',
            'url' => 'http://example.com',
            'admin_email' => 'admin@example.com',
        ];
        return $info[$show] ?? '';
    }
}

// Mock esc_html
if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

// Mock esc_url
if (!function_exists('esc_url')) {
    function esc_url($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

// Mock esc_attr
if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

// Mock sanitize_key
if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        $key = strtolower($key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key);
    }
}

// Mock wp_generate_password
if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($special_chars) {
            $chars .= '!@#$%^&*()';
        }
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
}

// Mock trailingslashit
if (!function_exists('trailingslashit')) {
    function trailingslashit($string) {
        return rtrim($string, '/\\') . '/';
    }
}

// Mock wp_verify_nonce
if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        // For testing, return true for valid-looking nonces
        return !empty($nonce);
    }
}

// Mock wp_send_json_error
if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null) {
        // No-op for testing (normally exits)
    }
}

// Mock wp_send_json_success
if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null) {
        // No-op for testing (normally exits)
    }
}

// Mock wp_get_image_editor
if (!function_exists('wp_get_image_editor')) {
    function wp_get_image_editor($path, $args = []) {
        return new WP_Error('no_editor', 'No image editor available');
    }
}

// Mock WP_Error class
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;
        private $data;

        public function __construct($code = '', $message = '', $data = '') {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code() {
            return $this->code;
        }

        public function get_error_message() {
            return $this->message;
        }

        public function get_error_data() {
            return $this->data;
        }
    }
}

// Mock date_i18n
if (!function_exists('date_i18n')) {
    function date_i18n($format, $timestamp = null) {
        if ($timestamp === null) {
            $timestamp = time();
        }
        return date($format, $timestamp);
    }
}

// Mock $wpdb global
global $wpdb;
if (!isset($wpdb)) {
    $wpdb = new class {
        public $prefix = 'wp_';
        public $last_error = '';
        public $insert_id = 0;
        private $mock_results = [];

        public function prepare($query, ...$args) {
            return vsprintf(str_replace(['%d', '%s'], ['%d', '%s'], $query), $args);
        }

        public function query($query) {
            return true;
        }

        public function get_var($query) {
            return 0;
        }

        public function get_row($query, $output = OBJECT) {
            return null;
        }

        public function get_results($query, $output = OBJECT) {
            return [];
        }

        public function insert($table, $data, $format = null) {
            $this->insert_id = rand(1, 10000);
            return 1;
        }

        public function update($table, $data, $where, $format = null, $where_format = null) {
            return 1;
        }

        public function delete($table, $where, $where_format = null) {
            return 1;
        }

        public function set_mock_results($results) {
            $this->mock_results = $results;
        }

        public function esc_like($text) {
            return addcslashes($text, '_%\\');
        }
    };
}

// Define OBJECT constant if not exists
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

// Autoload classes
spl_autoload_register(function ($class) {
    $prefix = 'Peanut_Festival_';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $file = PEANUT_FESTIVAL_PATH . 'includes/class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
