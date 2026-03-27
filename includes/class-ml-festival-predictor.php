<?php
/**
 * ML Festival Predictor
 * Integrates with the ML microservice for attendance prediction,
 * schedule optimization, and vendor placement analytics.
 *
 * @package Peanut_Festival
 */

if (!defined('ABSPATH')) {
    exit;
}

class Peanut_Festival_ML_Predictor {

    private static ?Peanut_Festival_ML_Predictor $instance = null;
    private const ML_SERVICE_URL = 'http://127.0.0.1:8100';
    private const TRANSIENT_PREFIX = 'pf_ml_';
    private const TRANSIENT_TTL = 15 * MINUTE_IN_SECONDS;  // 15 minutes
    private const TRAINING_HOOK = 'peanut_festival_ml_train_weekly';

    public static function get_instance(): Peanut_Festival_ML_Predictor {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->schedule_training();
    }

    /**
     * Check if ML service is available
     */
    public function is_available(): bool {
        $response = wp_remote_head(self::ML_SERVICE_URL . '/health', [
            'timeout' => 2,
            'blocking' => true,
        ]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Get ML service configuration from plugin settings
     */
    private function get_ml_config(): array {
        $settings = Peanut_Festival_Settings::get();

        return [
            'api_key' => $settings['ml_api_key'] ?? '',
            'enabled' => !empty($settings['ml_enabled']),
            'auto_train' => !empty($settings['ml_auto_train']),
        ];
    }

    /**
     * Make HTTP request to ML service
     *
     * @param string $endpoint The API endpoint (e.g., '/festival/predict-attendance')
     * @param array $data Request body data
     * @param string $method HTTP method (POST, GET)
     * @return array|WP_Error Response data or error
     */
    private function make_request(
        string $endpoint,
        array $data = [],
        string $method = 'POST'
    ) {
        $config = $this->get_ml_config();

        if (!$config['enabled']) {
            return new WP_Error(
                'ml_disabled',
                'ML service is disabled in plugin settings'
            );
        }

        $url = self::ML_SERVICE_URL . $endpoint;

        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ];

        if (!empty($config['api_key'])) {
            $args['headers']['X-ML-API-Key'] = $config['api_key'];
        }

        if ($method === 'POST' && !empty($data)) {
            $args['body'] = wp_json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            error_log('Peanut Festival ML Error: ' . $response->get_error_message());
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200) {
            error_log("Peanut Festival ML HTTP $code: $body");
            return new WP_Error(
                'ml_http_error',
                "ML service returned HTTP $code",
                ['response_code' => $code, 'body' => $body]
            );
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Peanut Festival ML JSON Error: " . json_last_error_msg());
            return new WP_Error(
                'ml_json_error',
                'Invalid JSON response from ML service'
            );
        }

        return $decoded;
    }

    /**
     * Predict attendance for a show
     *
     * @param array $show_data Show parameters:
     *   - festival_id (int)
     *   - show_id (int, optional)
     *   - venue_capacity (int)
     *   - day_of_week (int, 0-6)
     *   - time_of_day (int, 0-23)
     *   - performer_count (int)
     *   - ticket_price (float)
     *   - weather_forecast (string, optional)
     *   - historical_shows (int, optional)
     *
     * @return array Prediction result or error
     */
    public function predict_attendance(array $show_data): array {
        if (!$this->is_available()) {
            return ['error' => 'ML service unavailable'];
        }

        // Check transient cache
        $cache_key = self::TRANSIENT_PREFIX . 'attendance_' . md5(serialize($show_data));
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Validate required fields
        $required = ['festival_id', 'venue_capacity', 'day_of_week', 'time_of_day', 'performer_count', 'ticket_price'];
        foreach ($required as $field) {
            if (!isset($show_data[$field])) {
                return ['error' => "Missing required field: $field"];
            }
        }

        // Prepare request data
        $request_data = [
            'festival_id' => (int) $show_data['festival_id'],
            'show_id' => isset($show_data['show_id']) ? (int) $show_data['show_id'] : null,
            'venue_capacity' => (int) $show_data['venue_capacity'],
            'day_of_week' => (int) $show_data['day_of_week'],
            'time_of_day' => (int) $show_data['time_of_day'],
            'performer_count' => (int) $show_data['performer_count'],
            'ticket_price' => (float) $show_data['ticket_price'],
            'weather_forecast' => $show_data['weather_forecast'] ?? null,
            'historical_shows' => (int) ($show_data['historical_shows'] ?? 0),
        ];

        $result = $this->make_request('/festival/predict-attendance', $request_data);

        if (is_wp_error($result)) {
            return ['error' => $result->get_error_message()];
        }

        // Cache the result
        set_transient($cache_key, $result, self::TRANSIENT_TTL);

        return $result;
    }

    /**
     * Optimize show scheduling
     *
     * @param int $festival_id Festival ID
     * @param array $shows Array of shows with:
     *   - performer_id (int)
     *   - duration_minutes (int)
     *   - popularity_score (float, 0-100)
     *
     * @return array Optimization result or error
     */
    public function optimize_schedule(int $festival_id, array $shows): array {
        if (!$this->is_available()) {
            return ['error' => 'ML service unavailable'];
        }

        $cache_key = self::TRANSIENT_PREFIX . 'schedule_' . $festival_id . '_' . md5(serialize($shows));
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Validate shows array
        if (empty($shows)) {
            return ['error' => 'No shows provided for optimization'];
        }

        // Prepare request data
        $request_data = [
            'festival_id' => $festival_id,
            'shows' => array_map(function($show) {
                return [
                    'performer_id' => (int) ($show['performer_id'] ?? 0),
                    'duration_minutes' => (int) ($show['duration_minutes'] ?? 60),
                    'popularity_score' => (float) ($show['popularity_score'] ?? 50.0),
                ];
            }, $shows),
        ];

        $result = $this->make_request('/festival/optimize-schedule', $request_data);

        if (is_wp_error($result)) {
            return ['error' => $result->get_error_message()];
        }

        set_transient($cache_key, $result, self::TRANSIENT_TTL);

        return $result;
    }

    /**
     * Recommend vendor/sponsor placement
     *
     * @param int $festival_id Festival ID
     * @param array $config Configuration with:
     *   - vendor_count (int)
     *   - venue_layout_zones (array of zone names)
     *
     * @return array Placement recommendations or error
     */
    public function recommend_vendor_placement(int $festival_id, array $config): array {
        if (!$this->is_available()) {
            return ['error' => 'ML service unavailable'];
        }

        $cache_key = self::TRANSIENT_PREFIX . 'vendor_' . $festival_id . '_' . md5(serialize($config));
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Validate config
        if (empty($config['vendor_count']) || empty($config['venue_layout_zones'])) {
            return ['error' => 'Missing vendor_count or venue_layout_zones'];
        }

        $request_data = [
            'festival_id' => $festival_id,
            'vendor_count' => (int) $config['vendor_count'],
            'venue_layout_zones' => (array) $config['venue_layout_zones'],
        ];

        $result = $this->make_request('/festival/recommend-vendor-placement', $request_data);

        if (is_wp_error($result)) {
            return ['error' => $result->get_error_message()];
        }

        set_transient($cache_key, $result, self::TRANSIENT_TTL);

        return $result;
    }

    /**
     * Train models from database
     * Called weekly via cron, can also be called manually
     */
    public function train_model(): array {
        if (!$this->is_available()) {
            return ['error' => 'ML service unavailable'];
        }

        $result = $this->make_request('/festival/train', [], 'POST');

        if (!is_wp_error($result)) {
            // Clear all prediction caches after training
            $this->clear_prediction_cache();

            // Log training event
            error_log('Peanut Festival ML Training completed: ' . wp_json_encode($result));
        }

        return is_wp_error($result) ? ['error' => $result->get_error_message()] : $result;
    }

    /**
     * Get model statistics
     */
    public function get_model_stats(): array {
        if (!$this->is_available()) {
            return ['error' => 'ML service unavailable'];
        }

        $result = $this->make_request('/festival/stats', [], 'GET');

        return is_wp_error($result) ? ['error' => $result->get_error_message()] : $result;
    }

    /**
     * Clear all prediction caches
     */
    private function clear_prediction_cache(): void {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
            self::TRANSIENT_PREFIX . '%'
        ));
    }

    /**
     * Schedule weekly model training
     */
    private function schedule_training(): void {
        if (!wp_next_scheduled(self::TRAINING_HOOK)) {
            wp_schedule_event(
                time() + WEEK_IN_SECONDS,
                'weekly',
                self::TRAINING_HOOK
            );
        }

        add_action(self::TRAINING_HOOK, [$this, 'train_model']);
    }

    /**
     * Clear scheduled training on deactivation
     */
    public static function clear_scheduled_training(): void {
        $timestamp = wp_next_scheduled(self::TRAINING_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::TRAINING_HOOK);
        }
    }

    /**
     * Hook into show creation to auto-predict attendance
     * Called when a new show is created (if enabled in settings)
     */
    public function maybe_auto_predict_show(int $show_id): void {
        $config = $this->get_ml_config();

        if (!$config['enabled'] || empty($config['auto_train'])) {
            return;
        }

        $show = Peanut_Festival_Shows::get_instance()->get_show($show_id);
        if (!$show) {
            return;
        }

        // Get venue and performer info
        $venue = Peanut_Festival_Venues::get_instance()->get_venue($show['venue_id'] ?? 0);
        $start_time = isset($show['start_time']) ? new DateTime($show['start_time']) : null;

        if (!$venue || !$start_time) {
            return;
        }

        // Prepare show data
        $show_data = [
            'festival_id' => (int) $show['festival_id'],
            'show_id' => $show_id,
            'venue_capacity' => (int) ($venue['capacity'] ?? 500),
            'day_of_week' => (int) $start_time->format('w'),
            'time_of_day' => (int) $start_time->format('H'),
            'performer_count' => 1,
            'ticket_price' => 25.0,
            'historical_shows' => 0,
        ];

        // Make prediction
        $prediction = $this->predict_attendance($show_data);

        if (isset($prediction['predicted_attendance'])) {
            // Store prediction as post meta for reference
            update_post_meta(
                $show_id,
                '_ml_attendance_prediction',
                $prediction['predicted_attendance']
            );

            update_post_meta(
                $show_id,
                '_ml_prediction_confidence',
                $prediction['confidence_interval'] ?? []
            );

            update_post_meta(
                $show_id,
                '_ml_prediction_date',
                current_time('mysql')
            );
        }
    }
}
