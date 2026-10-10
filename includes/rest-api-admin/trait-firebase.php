<?php
/**
 * REST API Admin Firebase Trait
 *
 * @package    Peanut_Festival
 * @subpackage Includes/REST_API_Admin
 * @since      1.2.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait Peanut_Festival_REST_Admin_Firebase
 *
 * Handles Firebase integration endpoints.
 *
 * @since 1.2.1
 */
trait Peanut_Festival_REST_Admin_Firebase {

    /**
     * Get Firebase settings.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_firebase_settings(\WP_REST_Request $request): \WP_REST_Response {
        $settings = Peanut_Festival_Settings::get();

        // Return Firebase settings (mask sensitive data)
        $firebase_settings = [
            'enabled' => !empty($settings['firebase_enabled']),
            'project_id' => $settings['firebase_project_id'] ?? '',
            'database_url' => $settings['firebase_database_url'] ?? '',
            'api_key' => $settings['firebase_api_key'] ?? '',
            'vapid_key' => $settings['firebase_vapid_key'] ?? '',
            'credentials_file' => $settings['firebase_credentials_file'] ?? '',
            'credentials_uploaded' => !empty($settings['firebase_credentials_file']) && file_exists($settings['firebase_credentials_file']),
        ];

        return new \WP_REST_Response([
            'success' => true,
            'data' => $firebase_settings,
        ]);
    }

    /**
     * Update Firebase settings.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function update_firebase_settings(\WP_REST_Request $request): \WP_REST_Response {
        $data = $request->get_json_params();

        // The service account carries a private key: administrators only.
        if (!empty($data['credentials_json']) && !current_user_can('manage_options')) {
            return new \WP_REST_Response([
                'success' => false,
                'code' => 'forbidden_setting',
                'message' => 'Only administrators can upload Firebase service account credentials.',
            ], 403);
        }

        $updates = [];

        if (isset($data['enabled'])) {
            $updates['firebase_enabled'] = (bool) $data['enabled'];
        }

        if (isset($data['project_id'])) {
            $updates['firebase_project_id'] = sanitize_text_field($data['project_id']);
        }

        if (isset($data['database_url'])) {
            $updates['firebase_database_url'] = esc_url_raw($data['database_url']);
        }

        if (isset($data['api_key'])) {
            $updates['firebase_api_key'] = sanitize_text_field($data['api_key']);
        }

        if (isset($data['vapid_key'])) {
            $updates['firebase_vapid_key'] = sanitize_text_field($data['vapid_key']);
        }

        // Handle credentials JSON upload (base64 encoded)
        if (!empty($data['credentials_json'])) {
            $credentials_json = base64_decode($data['credentials_json'], true);

            // Validate base64 decoding succeeded
            if ($credentials_json === false) {
                Peanut_Festival_Logger::warning('Invalid base64 in Firebase credentials upload');
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Invalid credentials format (base64 decoding failed)',
                ], 400);
            }

            $decoded = json_decode($credentials_json, true);

            // Validate JSON structure
            if (json_last_error() !== JSON_ERROR_NONE) {
                Peanut_Festival_Logger::warning('Invalid JSON in Firebase credentials upload', [
                    'json_error' => json_last_error_msg(),
                ]);
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Invalid JSON format in credentials',
                ], 400);
            }

            // SECURITY: Validate required Firebase service account fields
            $required_fields = ['type', 'project_id', 'private_key_id', 'private_key', 'client_email'];
            $missing_fields = [];
            foreach ($required_fields as $field) {
                if (empty($decoded[$field])) {
                    $missing_fields[] = $field;
                }
            }

            if (!empty($missing_fields)) {
                Peanut_Festival_Logger::warning('Incomplete Firebase credentials', [
                    'missing_fields' => $missing_fields,
                ]);
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Invalid Firebase service account credentials. Missing required fields: ' . implode(', ', $missing_fields),
                ], 400);
            }

            // Validate type is service_account
            if ($decoded['type'] !== 'service_account') {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Invalid credentials type. Expected "service_account", got "' . sanitize_text_field($decoded['type']) . '"',
                ], 400);
            }

            // Validate private_key format (should start with -----BEGIN PRIVATE KEY-----)
            if (strpos($decoded['private_key'], '-----BEGIN PRIVATE KEY-----') !== 0) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Invalid private key format in credentials',
                ], 400);
            }

            // Validate client_email format
            if (!filter_var($decoded['client_email'], FILTER_VALIDATE_EMAIL)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Invalid client_email format in credentials',
                ], 400);
            }

            // Save credentials file
            $upload_dir = wp_upload_dir();

            // Ensure upload directory is valid
            if (!empty($upload_dir['error'])) {
                Peanut_Festival_Logger::error('Upload directory error', [
                    'error' => $upload_dir['error'],
                ]);
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Server configuration error: Unable to access upload directory',
                ], 500);
            }

            $credentials_dir = $upload_dir['basedir'] . '/peanut-festival/';

            // Create directory if it doesn't exist
            if (!file_exists($credentials_dir)) {
                if (!wp_mkdir_p($credentials_dir)) {
                    Peanut_Festival_Logger::error('Failed to create credentials directory', [
                        'path' => $credentials_dir,
                    ]);
                    return new \WP_REST_Response([
                        'success' => false,
                        'message' => 'Failed to create secure storage directory',
                    ], 500);
                }

                // Add .htaccess to protect directory (Apache)
                $htaccess_result = file_put_contents($credentials_dir . '.htaccess', "Order deny,allow\nDeny from all");
                if ($htaccess_result === false) {
                    Peanut_Festival_Logger::warning('Failed to create .htaccess protection', [
                        'path' => $credentials_dir,
                    ]);
                }

                // Add index.php for additional protection
                file_put_contents($credentials_dir . 'index.php', '<?php // Silence is golden');
            }

            $credentials_file = $credentials_dir . 'firebase-credentials.json';

            // Write credentials file with error handling
            $write_result = file_put_contents($credentials_file, $credentials_json);
            if ($write_result === false) {
                Peanut_Festival_Logger::error('Failed to write Firebase credentials file', [
                    'path' => $credentials_file,
                ]);
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Failed to save credentials file',
                ], 500);
            }

            // Set restrictive permissions
            if (!chmod($credentials_file, 0600)) {
                Peanut_Festival_Logger::warning('Failed to set permissions on credentials file', [
                    'path' => $credentials_file,
                ]);
            }

            Peanut_Festival_Logger::info('Firebase credentials saved successfully', [
                'project_id' => $decoded['project_id'],
            ]);

            $updates['firebase_credentials_file'] = $credentials_file;

            // Auto-populate project ID from credentials
            if (empty($updates['firebase_project_id'])) {
                $updates['firebase_project_id'] = $decoded['project_id'];
            }
        }

        if (!empty($updates)) {
            Peanut_Festival_Settings::update($updates);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Firebase settings updated',
        ]);
    }

    /**
     * Test Firebase connection.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function test_firebase(\WP_REST_Request $request): \WP_REST_Response {
        if (!Peanut_Festival_Firebase::is_enabled()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Firebase is not enabled',
            ]);
        }

        try {
            $firebase = Peanut_Festival_Firebase::get_instance();
            $result = $firebase->write('_test/connection', [
                'tested_at' => gmdate('c'),
                'tested_by' => get_current_user_id(),
            ]);

            if ($result) {
                // Clean up test data
                $firebase->delete('_test');

                return new \WP_REST_Response([
                    'success' => true,
                    'message' => 'Firebase connection successful',
                ]);
            }

            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to write to Firebase',
            ]);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Firebase error: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Sync festival data to Firebase.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function sync_firebase(\WP_REST_Request $request): \WP_REST_Response {
        $festival_id = (int) $request->get_param('festival_id') ?: Peanut_Festival_Settings::get_active_festival_id();

        if (!$festival_id) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'No festival selected',
            ], 400);
        }

        if (!Peanut_Festival_Firebase::is_enabled()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Firebase is not enabled',
            ]);
        }

        try {
            Peanut_Festival_Realtime_Sync::init_festival_sync($festival_id);

            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Festival synced to Firebase',
            ]);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Sync error: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Send Firebase push notification.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function send_firebase_notification(\WP_REST_Request $request): \WP_REST_Response {
        if (!Peanut_Festival_Firebase::is_enabled()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Firebase is not enabled',
            ]);
        }

        $data = $request->get_json_params();
        $title = sanitize_text_field($data['title'] ?? '');
        $body = sanitize_textarea_field($data['body'] ?? '');
        $topic = sanitize_text_field($data['topic'] ?? '');
        $link = esc_url_raw($data['link'] ?? '');

        if (empty($title) || empty($body)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Title and body are required',
            ], 400);
        }

        if (empty($topic)) {
            // Default to active festival topic
            $festival_id = Peanut_Festival_Settings::get_active_festival_id();
            $topic = $festival_id ? 'festival_' . $festival_id : null;
        }

        if (!$topic) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'No topic specified and no active festival',
            ], 400);
        }

        try {
            $firebase = Peanut_Festival_Firebase::get_instance();
            $result = $firebase->send_notification($topic, $title, $body, [
                'link' => $link,
                'type' => 'announcement',
            ]);

            return new \WP_REST_Response([
                'success' => $result,
                'message' => $result ? 'Notification sent' : 'Failed to send notification',
            ]);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Notification error: ' . $e->getMessage(),
            ]);
        }
    }
}
