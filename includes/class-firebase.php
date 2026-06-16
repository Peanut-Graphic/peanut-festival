<?php
/**
 * Firebase Integration Class
 *
 * Handles Firebase Realtime Database and Cloud Messaging integration.
 * Uses REST API for compatibility (no Composer dependencies required).
 *
 * @package    Peanut_Festival
 * @subpackage Includes
 * @since      1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Peanut_Festival_Firebase
 *
 * Provides Firebase integration for real-time updates and push notifications.
 *
 * @since 1.2.0
 */
class Peanut_Festival_Firebase {

    /**
     * Singleton instance.
     *
     * @var Peanut_Festival_Firebase|null
     */
    private static ?Peanut_Festival_Firebase $instance = null;

    /**
     * Firebase configuration.
     *
     * @var array
     */
    private array $config = [];

    /**
     * Whether Firebase is configured and enabled.
     *
     * @var bool
     */
    private bool $enabled = false;

    /**
     * Google OAuth access token.
     *
     * @var string|null
     */
    private ?string $access_token = null;

    /**
     * Token expiration timestamp.
     *
     * @var int
     */
    private int $token_expires = 0;

    /**
     * Get singleton instance.
     *
     * @return Peanut_Festival_Firebase
     */
    public static function get_instance(): Peanut_Festival_Firebase {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Reset the singleton instance.
     *
     * Intended for tests, which mutate the underlying settings between cases
     * and need the configuration re-read on the next get_instance() call.
     *
     * @return void
     */
    public static function reset_instance(): void {
        self::$instance = null;
    }

    /**
     * Private constructor.
     */
    private function __construct() {
        $this->load_config();
        $this->init_hooks();
    }

    /**
     * Load Firebase configuration from settings.
     */
    private function load_config(): void {
        $this->config = [
            'enabled' => (bool) Peanut_Festival_Settings::get('firebase_enabled', false),
            'project_id' => Peanut_Festival_Settings::get('firebase_project_id', ''),
            'database_url' => Peanut_Festival_Settings::get('firebase_database_url', ''),
            'api_key' => Peanut_Festival_Settings::get('firebase_api_key', ''),
            'service_account' => Peanut_Festival_Settings::get('firebase_service_account', ''),
            'vapid_key' => Peanut_Festival_Settings::get('firebase_vapid_key', ''),
        ];

        $this->enabled = $this->config['enabled']
            && !empty($this->config['project_id'])
            && !empty($this->config['database_url']);
    }

    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks(): void {
        if (!$this->enabled) {
            return;
        }

        // Sync data to Firebase on changes
        add_action('peanut_festival_vote_recorded', [$this, 'sync_vote'], 10, 2);
        add_action('peanut_festival_match_vote_recorded', [$this, 'sync_match_vote'], 10, 2);
        add_action('peanut_festival_show_status_changed', [$this, 'sync_show_status'], 10, 2);
        add_action('peanut_festival_performer_checkin', [$this, 'sync_performer_checkin'], 10, 2);

        // Push notification triggers
        add_action('peanut_festival_voting_starting', [$this, 'notify_voting_starting'], 10, 2);
        add_action('peanut_festival_performer_on_stage', [$this, 'notify_performer_on_stage'], 10, 2);
        add_action('peanut_festival_winner_announced', [$this, 'notify_winner_announced'], 10, 2);
    }

    /**
     * Check if Firebase is enabled and configured.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return self::get_instance()->enabled;
    }

    /**
     * Get Firebase configuration for frontend.
     *
     * @return array Safe config for client-side use
     */
    public static function get_client_config(): array {
        $instance = self::get_instance();

        if (!$instance->enabled) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'projectId' => $instance->config['project_id'],
            'databaseURL' => $instance->config['database_url'],
            'apiKey' => $instance->config['api_key'],
            'vapidKey' => $instance->config['vapid_key'],
        ];
    }

    // =========================================
    // Realtime Database Operations
    // =========================================

    /**
     * Get OAuth2 access token for Firebase REST API.
     *
     * @return string|null Access token or null on failure
     */
    private function get_access_token(): ?string {
        // Return cached token if still valid
        if ($this->access_token && $this->token_expires > time()) {
            return $this->access_token;
        }

        $service_account = $this->config['service_account'];
        if (empty($service_account)) {
            return null;
        }

        // Try to decode service account JSON
        $sa_data = json_decode($service_account, true);
        if (!$sa_data || empty($sa_data['private_key']) || empty($sa_data['client_email'])) {
            Peanut_Festival_Logger::error('Invalid Firebase service account configuration');
            return null;
        }

        // Create JWT for OAuth2 token request
        $jwt = $this->create_jwt($sa_data);
        if (!$jwt) {
            return null;
        }

        // Exchange JWT for access token
        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            Peanut_Festival_Logger::error('Firebase token request failed: ' . $response->get_error_message());
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) {
            Peanut_Festival_Logger::error('Firebase token response invalid');
            return null;
        }

        $this->access_token = $body['access_token'];
        $this->token_expires = time() + ($body['expires_in'] ?? 3600) - 60;

        return $this->access_token;
    }

    /**
     * Create JWT for Google OAuth2.
     *
     * @param array $sa_data Service account data
     * @return string|null JWT token
     */
    private function create_jwt(array $sa_data): ?string {
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $now = time();
        $claims = [
            'iss' => $sa_data['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.database https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $header_encoded = $this->base64url_encode(json_encode($header));
        $claims_encoded = $this->base64url_encode(json_encode($claims));
        $signature_input = $header_encoded . '.' . $claims_encoded;

        // Sign with private key
        $private_key = openssl_pkey_get_private($sa_data['private_key']);
        if (!$private_key) {
            Peanut_Festival_Logger::error('Invalid Firebase private key');
            return null;
        }

        $signature = '';
        if (!openssl_sign($signature_input, $signature, $private_key, OPENSSL_ALGO_SHA256)) {
            Peanut_Festival_Logger::error('Failed to sign Firebase JWT');
            return null;
        }

        return $signature_input . '.' . $this->base64url_encode($signature);
    }

    /**
     * Base64 URL encode.
     *
     * @param string $data Data to encode
     * @return string Encoded string
     */
    private function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Write data to Firebase Realtime Database.
     *
     * @param string $path Database path
     * @param mixed $data Data to write
     * @return bool Success
     */
    public function write(string $path, mixed $data): bool {
        if (!$this->enabled) {
            return false;
        }

        $token = $this->get_access_token();
        if (!$token) {
            // Fallback to API key for public writes (if allowed by rules)
            $url = rtrim($this->config['database_url'], '/') . '/' . ltrim($path, '/') . '.json';
            if (!empty($this->config['api_key'])) {
                $url .= '?auth=' . $this->config['api_key'];
            }
        } else {
            $url = rtrim($this->config['database_url'], '/') . '/' . ltrim($path, '/') . '.json';
        }

        $response = wp_remote_request($url, [
            'method' => 'PUT',
            'headers' => $token ? ['Authorization' => 'Bearer ' . $token] : [],
            'body' => json_encode($data),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            Peanut_Festival_Logger::error('Firebase write failed: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        return $code >= 200 && $code < 300;
    }

    /**
     * Read data from Firebase Realtime Database.
     *
     * @param string $path Database path
     * @return mixed Data or null on failure
     */
    public function read(string $path): mixed {
        if (!$this->enabled) {
            return null;
        }

        $url = rtrim($this->config['database_url'], '/') . '/' . ltrim($path, '/') . '.json';

        $token = $this->get_access_token();
        $headers = $token ? ['Authorization' => 'Bearer ' . $token] : [];

        $response = wp_remote_get($url, [
            'headers' => $headers,
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Delete data from Firebase Realtime Database.
     *
     * @param string $path Database path
     * @return bool Success
     */
    public function delete(string $path): bool {
        if (!$this->enabled) {
            return false;
        }

        $url = rtrim($this->config['database_url'], '/') . '/' . ltrim($path, '/') . '.json';

        $token = $this->get_access_token();

        $response = wp_remote_request($url, [
            'method' => 'DELETE',
            'headers' => $token ? ['Authorization' => 'Bearer ' . $token] : [],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        return $code >= 200 && $code < 300;
    }

    // =========================================
    // Cloud Messaging (Push Notifications)
    // =========================================

    /**
     * Send push notification via Firebase Cloud Messaging.
     *
     * @param string $topic Topic to send to (e.g., 'festival_123')
     * @param array $notification Notification data (title, body, icon, etc.)
     * @param array $data Additional data payload
     * @return bool Success
     */
    public function send_notification(string $topic, array $notification, array $data = []): bool {
        if (!$this->enabled) {
            return false;
        }

        $token = $this->get_access_token();
        if (!$token) {
            return false;
        }

        $url = 'https://fcm.googleapis.com/v1/projects/' . $this->config['project_id'] . '/messages:send';

        $message = [
            'message' => [
                'topic' => $topic,
                'notification' => array_merge([
                    'title' => $notification['title'] ?? 'Peanut Festival',
                    'body' => $notification['body'] ?? '',
                ], $notification),
                'webpush' => [
                    'fcm_options' => [
                        'link' => $notification['link'] ?? home_url(),
                    ],
                ],
            ],
        ];

        if (!empty($data)) {
            $message['message']['data'] = array_map('strval', $data);
        }

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($message),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            Peanut_Festival_Logger::error('FCM send failed: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            Peanut_Festival_Logger::error('FCM send failed with code ' . $code . ': ' . wp_remote_retrieve_body($response));
            return false;
        }

        return true;
    }

    /**
     * Subscribe a device token to a topic.
     *
     * @param string $token Device FCM token
     * @param string $topic Topic name
     * @return bool Success
     */
    public function subscribe_to_topic(string $token, string $topic): bool {
        if (!$this->enabled) {
            return false;
        }

        $access_token = $this->get_access_token();
        if (!$access_token) {
            return false;
        }

        $url = 'https://iid.googleapis.com/iid/v1/' . $token . '/rel/topics/' . $topic;

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'timeout' => 10,
        ]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    // =========================================
    // Sync Handlers
    // =========================================

    /**
     * Sync vote to Firebase.
     *
     * @param string $show_slug Show slug
     * @param array $vote_data Vote data
     */
    public function sync_vote(string $show_slug, array $vote_data): void {
        $path = 'votes/' . sanitize_key($show_slug) . '/' . $vote_data['group_name'];

        // Get current totals and update
        $results = Peanut_Festival_Voting::get_results($show_slug, $vote_data['group_name']);

        $firebase_data = [];
        foreach ($results as $result) {
            $firebase_data[$result->performer_id] = [
                'name' => $result->performer_name,
                'score' => (int) $result->weighted_score,
                'votes' => (int) $result->total_votes,
            ];
        }

        $firebase_data['_meta'] = [
            'updated_at' => gmdate('c'),
            'total_votes' => array_sum(array_column($results, 'total_votes')),
        ];

        $this->write($path, $firebase_data);
    }

    /**
     * Sync match vote to Firebase.
     *
     * @param int $match_id Match ID
     * @param array $vote_data Vote data
     */
    public function sync_match_vote(int $match_id, array $vote_data): void {
        $match = Peanut_Festival_Competitions::get_match($match_id);
        if (!$match) {
            return;
        }

        $path = 'matches/' . $match_id;

        $this->write($path, [
            'performer_1_votes' => (int) $match->votes_performer_1,
            'performer_2_votes' => (int) $match->votes_performer_2,
            'status' => $match->status,
            'updated_at' => gmdate('c'),
        ]);
    }

    /**
     * Sync show status to Firebase.
     *
     * @param int $show_id Show ID
     * @param string $status New status
     */
    public function sync_show_status(int $show_id, string $status): void {
        $show = Peanut_Festival_Shows::get_by_id($show_id);
        if (!$show) {
            return;
        }

        $path = 'shows/' . $show_id;

        $this->write($path, [
            'title' => $show->title,
            'status' => $status,
            'now_playing' => $status === 'in_progress',
            'updated_at' => gmdate('c'),
        ]);
    }

    /**
     * Sync performer check-in to Firebase.
     *
     * @param int $performer_id Performer ID
     * @param array $checkin_data Check-in data
     */
    public function sync_performer_checkin(int $performer_id, array $checkin_data): void {
        $performer = Peanut_Festival_Performers::get_by_id($performer_id);
        if (!$performer) {
            return;
        }

        $path = 'performers/' . $performer_id . '/checkin';

        $this->write($path, [
            'checked_in' => true,
            'checked_in_at' => gmdate('c'),
            'show_id' => $checkin_data['show_id'] ?? null,
        ]);
    }

    // =========================================
    // Push Notification Handlers
    // =========================================

    /**
     * Notify users that voting is starting soon.
     *
     * @param int $show_id Show ID
     * @param int $minutes_until Minutes until voting starts
     */
    public function notify_voting_starting(int $show_id, int $minutes_until): void {
        $show = Peanut_Festival_Shows::get_by_id($show_id);
        if (!$show) {
            return;
        }

        $topic = 'festival_' . $show->festival_id;

        $this->send_notification($topic, [
            'title' => 'Voting Starting Soon!',
            'body' => "Voting for \"{$show->title}\" starts in {$minutes_until} minutes!",
            'icon' => '/wp-content/plugins/peanut-festival/public/images/icon-192.png',
        ], [
            'type' => 'voting_starting',
            'show_id' => (string) $show_id,
        ]);
    }

    /**
     * Notify users that a performer is on stage.
     *
     * @param int $performer_id Performer ID
     * @param int $show_id Show ID
     */
    public function notify_performer_on_stage(int $performer_id, int $show_id): void {
        $performer = Peanut_Festival_Performers::get_by_id($performer_id);
        $show = Peanut_Festival_Shows::get_by_id($show_id);

        if (!$performer || !$show) {
            return;
        }

        $topic = 'festival_' . $show->festival_id;

        $this->send_notification($topic, [
            'title' => 'Now On Stage',
            'body' => "{$performer->name} is performing now at \"{$show->title}\"!",
            'icon' => $performer->photo_url ?? '/wp-content/plugins/peanut-festival/public/images/icon-192.png',
        ], [
            'type' => 'performer_on_stage',
            'performer_id' => (string) $performer_id,
            'show_id' => (string) $show_id,
        ]);
    }

    /**
     * Notify users of winner announcement.
     *
     * @param int $winner_id Winner performer ID
     * @param int $competition_id Competition or show ID
     */
    public function notify_winner_announced(int $winner_id, int $competition_id): void {
        $winner = Peanut_Festival_Performers::get_by_id($winner_id);
        $competition = Peanut_Festival_Competitions::get_by_id($competition_id);

        if (!$winner) {
            return;
        }

        $festival_id = $competition ? $competition->festival_id : null;
        if (!$festival_id) {
            return;
        }

        $topic = 'festival_' . $festival_id;

        $this->send_notification($topic, [
            'title' => 'Winner Announced!',
            'body' => "Congratulations to {$winner->name} for winning!",
            'icon' => $winner->photo_url ?? '/wp-content/plugins/peanut-festival/public/images/icon-192.png',
        ], [
            'type' => 'winner_announced',
            'winner_id' => (string) $winner_id,
            'competition_id' => (string) $competition_id,
        ]);
    }

    // =========================================
    // REST API Endpoints
    // =========================================

    /**
     * Register REST API routes.
     */
    public static function register_routes(): void {
        register_rest_route('peanut-festival/v1', '/firebase/config', [
            'methods' => 'GET',
            'callback' => [self::class, 'api_get_config'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('peanut-festival/v1', '/firebase/subscribe', [
            'methods' => 'POST',
            'callback' => [self::class, 'api_subscribe'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * API: Get Firebase config for client.
     *
     * @return WP_REST_Response
     */
    public static function api_get_config(): WP_REST_Response {
        return new WP_REST_Response([
            'success' => true,
            'config' => self::get_client_config(),
        ]);
    }

    /**
     * API: Subscribe device to festival topic.
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public static function api_subscribe(WP_REST_Request $request): WP_REST_Response {
        // Rate limiting to prevent abuse
        $rate_check = Peanut_Festival_Rate_Limiter::enforce('general');
        if (is_wp_error($rate_check)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $rate_check->get_error_message(),
            ], 429);
        }

        $token = sanitize_text_field($request->get_param('token'));
        $festival_id = (int) $request->get_param('festival_id');

        if (empty($token) || !$festival_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Token and festival_id are required',
            ], 400);
        }

        $topic = 'festival_' . $festival_id;
        $success = self::get_instance()->subscribe_to_topic($token, $topic);

        return new WP_REST_Response([
            'success' => $success,
            'topic' => $topic,
        ]);
    }
}
