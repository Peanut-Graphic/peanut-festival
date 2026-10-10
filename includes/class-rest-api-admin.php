<?php
/**
 * Admin REST API endpoints
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load REST API Admin traits.
require_once __DIR__ . '/rest-api-admin/trait-reports.php';
require_once __DIR__ . '/rest-api-admin/trait-firebase.php';

class Peanut_Festival_REST_API_Admin {

    use Peanut_Festival_REST_Admin_Reports;
    use Peanut_Festival_REST_Admin_Firebase;

    private const NAMESPACE = 'peanut-festival/v1/admin';

    /**
     * Valid date format periods for SQL queries (whitelist to prevent SQL injection)
     */
    private const VALID_PERIODS = [
        'daily' => '%Y-%m-%d',
        'weekly' => '%Y-%u',
        'monthly' => '%Y-%m',
    ];

    public function register_routes(): void {
        // Dashboard
        register_rest_route(self::NAMESPACE, '/dashboard/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_dashboard_stats'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Festivals
        $this->register_crud_routes('festivals', 'Peanut_Festival_Festivals');

        // Shows
        $this->register_crud_routes('shows', 'Peanut_Festival_Shows');

        register_rest_route(self::NAMESPACE, '/shows/(?P<id>\d+)/performers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_show_performers'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/shows/(?P<id>\d+)/performers', [
            'methods' => 'POST',
            'callback' => [$this, 'add_show_performer'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Performers
        $this->register_crud_routes('performers', 'Peanut_Festival_Performers');

        register_rest_route(self::NAMESPACE, '/performers/(?P<id>\d+)/review', [
            'methods' => 'POST',
            'callback' => [$this, 'review_performer'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/performers/(?P<id>\d+)/notify', [
            'methods' => 'POST',
            'callback' => [$this, 'notify_performer'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Venues
        $this->register_crud_routes('venues', 'Peanut_Festival_Venues');

        // Voting
        register_rest_route(self::NAMESPACE, '/voting/config/(?P<show_slug>[a-zA-Z0-9-_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_voting_config'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/voting/config/(?P<show_slug>[a-zA-Z0-9-_]+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'save_voting_config'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/voting/results/(?P<show_slug>[a-zA-Z0-9-_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_voting_results'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/voting/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'get_voting_logs'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/voting/calculate-finals', [
            'methods' => 'POST',
            'callback' => [$this, 'calculate_voting_finals'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Volunteers
        $this->register_crud_routes('volunteers', 'Peanut_Festival_Volunteers');

        register_rest_route(self::NAMESPACE, '/volunteers/shifts', [
            'methods' => 'GET',
            'callback' => [$this, 'get_volunteer_shifts'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/volunteers/shifts', [
            'methods' => 'POST',
            'callback' => [$this, 'create_volunteer_shift'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Vendors
        $this->register_crud_routes('vendors', 'Peanut_Festival_Vendors');

        // Sponsors
        $this->register_crud_routes('sponsors', 'Peanut_Festival_Sponsors');

        // Attendees & Tickets
        register_rest_route(self::NAMESPACE, '/attendees', [
            'methods' => 'GET',
            'callback' => [$this, 'get_attendees'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/attendees/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_attendee'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/attendees/(?P<id>\d+)/tickets', [
            'methods' => 'GET',
            'callback' => [$this, 'get_attendee_tickets'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/tickets/(?P<id>\d+)/check-in', [
            'methods' => 'POST',
            'callback' => [$this, 'check_in_ticket'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/coupons', [
            'methods' => 'GET',
            'callback' => [$this, 'get_coupons'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/coupons', [
            'methods' => 'POST',
            'callback' => [$this, 'create_coupon'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Messaging
        register_rest_route(self::NAMESPACE, '/messages/conversations', [
            'methods' => 'GET',
            'callback' => [$this, 'get_conversations'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/messages/(?P<conversation_id>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_messages'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/messages', [
            'methods' => 'POST',
            'callback' => [$this, 'send_message'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/messages/broadcast', [
            'methods' => 'POST',
            'callback' => [$this, 'send_broadcast'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/messages/(?P<conversation_id>[a-zA-Z0-9_-]+)/read', [
            'methods' => 'POST',
            'callback' => [$this, 'mark_messages_read'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Transactions
        register_rest_route(self::NAMESPACE, '/transactions', [
            'methods' => 'GET',
            'callback' => [$this, 'get_transactions'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/transactions', [
            'methods' => 'POST',
            'callback' => [$this, 'create_transaction'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/transactions/summary', [
            'methods' => 'GET',
            'callback' => [$this, 'get_transactions_summary'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Settings
        register_rest_route(self::NAMESPACE, '/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'get_settings'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/settings', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_settings'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Eventbrite
        register_rest_route(self::NAMESPACE, '/eventbrite/test', [
            'methods' => 'GET',
            'callback' => [$this, 'test_eventbrite'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/eventbrite/sync', [
            'methods' => 'POST',
            'callback' => [$this, 'sync_eventbrite'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Mailchimp
        register_rest_route(self::NAMESPACE, '/mailchimp/test', [
            'methods' => 'GET',
            'callback' => [$this, 'test_mailchimp'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/mailchimp/lists', [
            'methods' => 'GET',
            'callback' => [$this, 'get_mailchimp_lists'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/mailchimp/sync/performers', [
            'methods' => 'POST',
            'callback' => [$this, 'sync_performers_to_mailchimp'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/mailchimp/sync/volunteers', [
            'methods' => 'POST',
            'callback' => [$this, 'sync_volunteers_to_mailchimp'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/mailchimp/sync/attendees', [
            'methods' => 'POST',
            'callback' => [$this, 'sync_attendees_to_mailchimp'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/mailchimp/sync/all', [
            'methods' => 'POST',
            'callback' => [$this, 'sync_all_to_mailchimp'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Phase 3: Firebase
        register_rest_route(self::NAMESPACE, '/firebase/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'get_firebase_settings'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/firebase/settings', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_firebase_settings'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/firebase/test', [
            'methods' => 'POST',
            'callback' => [$this, 'test_firebase'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/firebase/sync', [
            'methods' => 'POST',
            'callback' => [$this, 'sync_firebase'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/firebase/send-notification', [
            'methods' => 'POST',
            'callback' => [$this, 'send_firebase_notification'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Flyer templates
        register_rest_route(self::NAMESPACE, '/flyer-templates', [
            'methods' => 'GET',
            'callback' => [$this, 'get_flyer_templates'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/flyer-templates', [
            'methods' => 'POST',
            'callback' => [$this, 'create_flyer_template'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/flyer-templates/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_flyer_template'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/flyer-templates/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_flyer_template'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/flyer-usage', [
            'methods' => 'GET',
            'callback' => [$this, 'get_flyer_usage'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Check-in
        register_rest_route(self::NAMESPACE, '/checkin/verify', [
            'methods' => 'POST',
            'callback' => [$this, 'verify_and_checkin'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/checkin/lookup', [
            'methods' => 'GET',
            'callback' => [$this, 'lookup_ticket'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Analytics/Reports
        register_rest_route(self::NAMESPACE, '/reports/overview', [
            'methods' => 'GET',
            'callback' => [$this, 'get_reports_overview'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/reports/ticket-sales', [
            'methods' => 'GET',
            'callback' => [$this, 'get_ticket_sales_report'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/reports/revenue', [
            'methods' => 'GET',
            'callback' => [$this, 'get_revenue_report'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/reports/activity', [
            'methods' => 'GET',
            'callback' => [$this, 'get_activity_report'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/reports/export/(?P<type>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'export_report'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);
    }

    private function register_crud_routes(string $resource, string $class): void {
        // List
        register_rest_route(self::NAMESPACE, "/{$resource}", [
            'methods' => 'GET',
            'callback' => function($request) use ($class) {
                return $this->get_list($request, $class);
            },
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Get single
        register_rest_route(self::NAMESPACE, "/{$resource}/(?P<id>\d+)", [
            'methods' => 'GET',
            'callback' => function($request) use ($class) {
                return $this->get_single($request, $class);
            },
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Create
        register_rest_route(self::NAMESPACE, "/{$resource}", [
            'methods' => 'POST',
            'callback' => function($request) use ($class) {
                return $this->create_item($request, $class);
            },
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Update
        register_rest_route(self::NAMESPACE, "/{$resource}/(?P<id>\d+)", [
            'methods' => 'PUT',
            'callback' => function($request) use ($class) {
                return $this->update_item($request, $class);
            },
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // Delete
        register_rest_route(self::NAMESPACE, "/{$resource}/(?P<id>\d+)", [
            'methods' => 'DELETE',
            'callback' => function($request) use ($class) {
                return $this->delete_item($request, $class);
            },
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);
    }

    public function check_admin_permission(): bool {
        return current_user_can('manage_options') || current_user_can('manage_pf_festival');
    }

    /**
     * Sanitize input data for database operations
     */
    private function sanitize_input(array $data, string $class): array {
        $sanitized = [];

        foreach ($data as $key => $value) {
            // Skip internal fields that shouldn't be set via API
            if (in_array($key, ['id', 'created_at', 'updated_at', 'reviewed_by', 'reviewed_at'], true)) {
                continue;
            }

            // Handle different field types
            if (is_null($value)) {
                $sanitized[$key] = null;
            } elseif (is_bool($value)) {
                $sanitized[$key] = $value ? 1 : 0;
            } elseif (is_int($value)) {
                $sanitized[$key] = (int) $value;
            } elseif (is_float($value)) {
                $sanitized[$key] = (float) $value;
            } elseif (is_array($value)) {
                $sanitized[$key] = wp_json_encode($value);
            } else {
                // String fields - apply appropriate sanitization
                $key_lower = strtolower($key);
                if (str_contains($key_lower, 'email')) {
                    $sanitized[$key] = sanitize_email($value);
                } elseif (str_contains($key_lower, 'url') || str_contains($key_lower, 'website') || str_contains($key_lower, 'photo')) {
                    $sanitized[$key] = esc_url_raw($value);
                } elseif (str_contains($key_lower, 'bio') || str_contains($key_lower, 'description') || str_contains($key_lower, 'notes') || str_contains($key_lower, 'content')) {
                    $sanitized[$key] = sanitize_textarea_field($value);
                } else {
                    $sanitized[$key] = sanitize_text_field($value);
                }
            }
        }

        return $sanitized;
    }

    // Generic CRUD handlers
    private function get_list(\WP_REST_Request $request, string $class): \WP_REST_Response {
        $args = $request->get_params();

        // Pagination parameters
        $page = max(1, (int) ($args['page'] ?? 1));
        $per_page = min(100, max(1, (int) ($args['per_page'] ?? 20)));
        $offset = ($page - 1) * $per_page;

        // Build filter args
        $filter_args = [];
        if (!empty($args['festival_id'])) {
            $filter_args['festival_id'] = (int) $args['festival_id'];
        }
        if (!empty($args['status'])) {
            $filter_args['status'] = sanitize_text_field($args['status']);
        }

        // Get items with pagination
        $items = $class::get_all(array_merge($filter_args, [
            'limit' => $per_page,
            'offset' => $offset,
            'order_by' => sanitize_text_field($args['order_by'] ?? 'id'),
            'order' => strtoupper($args['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC',
        ]));

        // Get total count for pagination
        $total = $class::count($filter_args);

        return Peanut_Festival_REST_Response::paginated($items, $total, $page, $per_page);
    }

    private function get_single(\WP_REST_Request $request, string $class): \WP_REST_Response {
        $id = (int) $request->get_param('id');
        $item = $class::get_by_id($id);

        if (!$item) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Not found'], 404);
        }

        return new \WP_REST_Response(['success' => true, 'data' => $item]);
    }

    private function create_item(\WP_REST_Request $request, string $class): \WP_REST_Response {
        $data = $this->sanitize_input($request->get_json_params(), $class);
        $id = $class::create($data);

        if (!$id) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Failed to create'], 500);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => $class::get_by_id($id),
        ], 201);
    }

    private function update_item(\WP_REST_Request $request, string $class): \WP_REST_Response {
        $id = (int) $request->get_param('id');
        $data = $this->sanitize_input($request->get_json_params(), $class);

        $result = $class::update($id, $data);

        if ($result === false) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Failed to update'], 500);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => $class::get_by_id($id),
        ]);
    }

    private function delete_item(\WP_REST_Request $request, string $class): \WP_REST_Response {
        $id = (int) $request->get_param('id');
        $result = $class::delete($id);

        if ($result === false) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Failed to delete'], 500);
        }

        return new \WP_REST_Response(['success' => true]);
    }

    // Dashboard
    public function get_dashboard_stats(\WP_REST_Request $request): \WP_REST_Response {
        $festival_id = Peanut_Festival_Settings::get_active_festival_id();

        if (!$festival_id) {
            return new \WP_REST_Response([
                'success' => true,
                'data' => null,
                'message' => 'No active festival selected',
            ]);
        }

        $stats = Peanut_Festival_Analytics::get_dashboard_stats($festival_id);

        return new \WP_REST_Response([
            'success' => true,
            'data' => $stats,
        ]);
    }

    // Performers
    public function review_performer(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');
        $status = sanitize_text_field($request->get_param('status'));
        $notes = sanitize_textarea_field($request->get_param('notes') ?? '');

        $valid_statuses = ['pending', 'under_review', 'accepted', 'rejected', 'waitlisted', 'confirmed'];
        if (!in_array($status, $valid_statuses)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Invalid status'], 400);
        }

        $result = Peanut_Festival_Performers::review($id, $status, $notes);

        return new \WP_REST_Response([
            'success' => $result !== false,
            'data' => Peanut_Festival_Performers::get_by_id($id),
        ]);
    }

    public function notify_performer(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');
        $performer = Peanut_Festival_Performers::get_by_id($id);

        if (!$performer) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Performer not found'], 404);
        }

        $result = Peanut_Festival_Notifications::notify_performer_status($id, $performer->application_status);

        return new \WP_REST_Response([
            'success' => $result,
            'message' => $result ? 'Notification sent' : 'Failed to send notification',
        ]);
    }

    // Shows
    public function get_show_performers(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');
        $performers = Peanut_Festival_Shows::get_performers($id);

        return new \WP_REST_Response(['success' => true, 'data' => $performers]);
    }

    public function add_show_performer(\WP_REST_Request $request): \WP_REST_Response {
        $show_id = (int) $request->get_param('id');
        $performer_id = (int) $request->get_param('performer_id');
        $data = $request->get_json_params();

        $result = Peanut_Festival_Shows::add_performer($show_id, $performer_id, $data);

        return new \WP_REST_Response([
            'success' => $result !== false,
            'data' => Peanut_Festival_Shows::get_performers($show_id),
        ]);
    }

    // Voting
    public function get_voting_config(\WP_REST_Request $request): \WP_REST_Response {
        $show_slug = $request->get_param('show_slug');
        $config = Peanut_Festival_Voting::get_show_config($show_slug);

        return new \WP_REST_Response(['success' => true, 'data' => $config]);
    }

    public function save_voting_config(\WP_REST_Request $request): \WP_REST_Response {
        $show_slug = $request->get_param('show_slug');
        $config = $request->get_json_params();

        Peanut_Festival_Voting::save_show_config($show_slug, $config);

        return new \WP_REST_Response(['success' => true]);
    }

    public function get_voting_results(\WP_REST_Request $request): \WP_REST_Response {
        $show_slug = $request->get_param('show_slug');
        $results = Peanut_Festival_Voting::get_results($show_slug);

        return new \WP_REST_Response(['success' => true, 'data' => $results]);
    }

    public function get_voting_logs(\WP_REST_Request $request): \WP_REST_Response {
        $show_slug = $request->get_param('show_slug') ?? '';
        $logs = Peanut_Festival_Voting::get_vote_logs($show_slug);

        return new \WP_REST_Response(['success' => true, 'data' => $logs]);
    }

    public function calculate_voting_finals(\WP_REST_Request $request): \WP_REST_Response {
        $show_slug = sanitize_text_field($request->get_param('show_slug'));
        $finals = Peanut_Festival_Voting::calculate_finals($show_slug);

        return new \WP_REST_Response(['success' => true, 'data' => $finals]);
    }

    // Volunteers
    public function get_volunteer_shifts(\WP_REST_Request $request): \WP_REST_Response {
        $args = $request->get_params();
        $shifts = Peanut_Festival_Volunteers::get_shifts($args);

        return new \WP_REST_Response(['success' => true, 'data' => $shifts]);
    }

    public function create_volunteer_shift(\WP_REST_Request $request): \WP_REST_Response {
        $data = $request->get_json_params();
        $id = Peanut_Festival_Volunteers::create_shift($data);

        return new \WP_REST_Response([
            'success' => $id !== false,
            'data' => ['id' => $id],
        ], $id ? 201 : 500);
    }

    // Transactions
    public function get_transactions(\WP_REST_Request $request): \WP_REST_Response {
        $args = $request->get_params();
        $transactions = Peanut_Festival_Transactions::get_all($args);

        return new \WP_REST_Response(['success' => true, 'data' => $transactions]);
    }

    public function create_transaction(\WP_REST_Request $request): \WP_REST_Response {
        $data = $request->get_json_params();
        $id = Peanut_Festival_Transactions::create($data);

        return new \WP_REST_Response([
            'success' => $id !== false,
            'data' => ['id' => $id],
        ], $id ? 201 : 500);
    }

    public function get_transactions_summary(\WP_REST_Request $request): \WP_REST_Response {
        $festival_id = (int) $request->get_param('festival_id') ?: Peanut_Festival_Settings::get_active_festival_id();
        $summary = Peanut_Festival_Transactions::get_summary($festival_id);

        return new \WP_REST_Response(['success' => true, 'data' => $summary]);
    }

    // Settings
    public function get_settings(\WP_REST_Request $request): \WP_REST_Response {
        // Secrets are masked for everyone, administrators included.
        return new \WP_REST_Response(['success' => true, 'data' => Peanut_Festival_Settings::get_for_rest()]);
    }

    public function update_settings(\WP_REST_Request $request): \WP_REST_Response {
        $data = $request->get_json_params();

        if (!is_array($data)) {
            return new \WP_REST_Response([
                'success' => false,
                'code' => 'invalid_body',
                'message' => 'Expected a JSON object of settings.',
            ], 400);
        }

        $result = Peanut_Festival_Settings::apply_rest_update($data, current_user_can('manage_options'));

        if (is_wp_error($result)) {
            $error_data = (array) $result->get_error_data();
            return new \WP_REST_Response([
                'success' => false,
                'code' => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], (int) ($error_data['status'] ?? 400));
        }

        return new \WP_REST_Response([
            'success' => true,
            'updated' => $result,
            'data' => Peanut_Festival_Settings::get_for_rest(),
        ]);
    }

    // Eventbrite
    public function test_eventbrite(\WP_REST_Request $request): \WP_REST_Response {
        $result = Peanut_Festival_Eventbrite::test_connection();

        return new \WP_REST_Response($result);
    }

    public function sync_eventbrite(\WP_REST_Request $request): \WP_REST_Response {
        $festival_id = (int) $request->get_param('festival_id') ?: Peanut_Festival_Settings::get_active_festival_id();

        if (!$festival_id) {
            return new \WP_REST_Response(['success' => false, 'message' => 'No festival selected'], 400);
        }

        $result = Peanut_Festival_Eventbrite::sync_events($festival_id);

        return new \WP_REST_Response([
            'success' => true,
            'data' => $result,
        ]);
    }

    // Messaging
    public function get_conversations(\WP_REST_Request $request): \WP_REST_Response {
        $festival_id = Peanut_Festival_Settings::get_active_festival_id();
        $user_id = get_current_user_id();

        if (!$festival_id) {
            return new \WP_REST_Response(['success' => true, 'data' => []]);
        }

        $conversations = Peanut_Festival_Messaging::get_conversations($festival_id, $user_id, 'admin');

        return new \WP_REST_Response(['success' => true, 'data' => $conversations]);
    }

    public function get_messages(\WP_REST_Request $request): \WP_REST_Response {
        $conversation_id = $request->get_param('conversation_id');
        $messages = Peanut_Festival_Messaging::get_messages($conversation_id);

        return new \WP_REST_Response(['success' => true, 'data' => $messages]);
    }

    public function send_message(\WP_REST_Request $request): \WP_REST_Response {
        $data = $request->get_json_params();
        $data['sender_id'] = get_current_user_id();
        $data['sender_type'] = 'admin';
        $data['festival_id'] = $data['festival_id'] ?? Peanut_Festival_Settings::get_active_festival_id();

        $id = Peanut_Festival_Messaging::send_message($data);

        return new \WP_REST_Response([
            'success' => $id !== false,
            'data' => ['id' => $id],
        ], $id ? 201 : 500);
    }

    public function send_broadcast(\WP_REST_Request $request): \WP_REST_Response {
        $data = $request->get_json_params();
        $festival_id = $data['festival_id'] ?? Peanut_Festival_Settings::get_active_festival_id();
        $sender_id = get_current_user_id();
        $group = sanitize_text_field($data['group'] ?? 'all');
        $subject = sanitize_text_field($data['subject'] ?? '');
        $content = wp_kses_post($data['content'] ?? '');

        if (!$festival_id || !$content) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Missing required fields'], 400);
        }

        $id = Peanut_Festival_Messaging::send_broadcast($festival_id, $sender_id, $group, $subject, $content);

        return new \WP_REST_Response([
            'success' => $id !== false,
            'data' => ['id' => $id],
        ], $id ? 201 : 500);
    }

    public function mark_messages_read(\WP_REST_Request $request): \WP_REST_Response {
        $conversation_id = $request->get_param('conversation_id');
        $user_id = get_current_user_id();

        Peanut_Festival_Messaging::mark_as_read($conversation_id, $user_id, 'admin');

        return new \WP_REST_Response(['success' => true]);
    }

    // Attendees & Tickets
    public function get_attendees(\WP_REST_Request $request): \WP_REST_Response {
        $args = $request->get_params();
        $attendees = Peanut_Festival_Attendees::get_all($args);
        $total = Peanut_Festival_Attendees::count(
            $args['festival_id'] ? ['festival_id' => $args['festival_id']] : []
        );

        return new \WP_REST_Response([
            'success' => true,
            'data' => $attendees,
            'total' => $total,
        ]);
    }

    public function get_attendee(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');
        $attendee = Peanut_Festival_Attendees::get_by_id($id);

        if (!$attendee) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Not found'], 404);
        }

        return new \WP_REST_Response(['success' => true, 'data' => $attendee]);
    }

    public function get_attendee_tickets(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');
        $tickets = Peanut_Festival_Attendees::get_tickets($id);

        return new \WP_REST_Response(['success' => true, 'data' => $tickets]);
    }

    public function check_in_ticket(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');
        $result = Peanut_Festival_Attendees::check_in_ticket($id);

        return new \WP_REST_Response([
            'success' => $result !== false,
            'message' => $result !== false ? 'Checked in successfully' : 'Failed to check in',
        ]);
    }

    public function get_coupons(\WP_REST_Request $request): \WP_REST_Response {
        $args = $request->get_params();
        $coupons = Peanut_Festival_Attendees::get_coupons($args);

        return new \WP_REST_Response(['success' => true, 'data' => $coupons]);
    }

    public function create_coupon(\WP_REST_Request $request): \WP_REST_Response {
        $data = $request->get_json_params();
        $id = Peanut_Festival_Attendees::create_coupon($data);

        return new \WP_REST_Response([
            'success' => $id !== false,
            'data' => ['id' => $id],
        ], $id ? 201 : 500);
    }

    // Flyer
    public function get_flyer_templates(\WP_REST_Request $request): \WP_REST_Response {
        $festival_id = $request->get_param('festival_id');
        $templates = Peanut_Festival_Flyer_Generator::get_templates($festival_id);

        // Decode JSON fields for each template
        foreach ($templates as &$template) {
            if (!empty($template->frame) && is_string($template->frame)) {
                $template->frame = json_decode($template->frame, true);
            }
            if (!empty($template->namebox) && is_string($template->namebox)) {
                $template->namebox = json_decode($template->namebox, true);
            }
        }

        return new \WP_REST_Response(['success' => true, 'data' => $templates]);
    }

    public function create_flyer_template(\WP_REST_Request $request): \WP_REST_Response {
        $data = $request->get_json_params();
        $id = Peanut_Festival_Flyer_Generator::create_template($data);

        if (!$id) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Failed to create template'], 500);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => ['id' => $id],
        ], 201);
    }

    public function update_flyer_template(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');
        $data = $request->get_json_params();

        $result = Peanut_Festival_Flyer_Generator::update_template($id, $data);

        if ($result === false) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Failed to update template'], 500);
        }

        $template = Peanut_Festival_Flyer_Generator::get_template($id);
        if ($template) {
            if (!empty($template->frame) && is_string($template->frame)) {
                $template->frame = json_decode($template->frame, true);
            }
            if (!empty($template->namebox) && is_string($template->namebox)) {
                $template->namebox = json_decode($template->namebox, true);
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => $template,
        ]);
    }

    public function delete_flyer_template(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');
        $result = Peanut_Festival_Flyer_Generator::delete_template($id);

        if ($result === false) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Failed to delete template'], 500);
        }

        return new \WP_REST_Response(['success' => true]);
    }

    public function get_flyer_usage(\WP_REST_Request $request): \WP_REST_Response {
        $limit = (int) ($request->get_param('limit') ?? 100);
        $usage = Peanut_Festival_Flyer_Generator::get_usage_log($limit);

        return new \WP_REST_Response(['success' => true, 'data' => $usage]);
    }

    // Mailchimp
    public function test_mailchimp(\WP_REST_Request $request): \WP_REST_Response {
        $result = Peanut_Festival_Mailchimp::test_connection();
        return new \WP_REST_Response($result);
    }

    public function get_mailchimp_lists(\WP_REST_Request $request): \WP_REST_Response {
        $result = Peanut_Festival_Mailchimp::get_lists();
        return new \WP_REST_Response($result);
    }

    public function sync_performers_to_mailchimp(\WP_REST_Request $request): \WP_REST_Response {
        $festival_id = (int) $request->get_param('festival_id') ?: Peanut_Festival_Settings::get_active_festival_id();
        $result = Peanut_Festival_Mailchimp::sync_performers($festival_id);
        return new \WP_REST_Response($result);
    }

    public function sync_volunteers_to_mailchimp(\WP_REST_Request $request): \WP_REST_Response {
        $festival_id = (int) $request->get_param('festival_id') ?: Peanut_Festival_Settings::get_active_festival_id();
        $result = Peanut_Festival_Mailchimp::sync_volunteers($festival_id);
        return new \WP_REST_Response($result);
    }

    public function sync_attendees_to_mailchimp(\WP_REST_Request $request): \WP_REST_Response {
        $festival_id = (int) $request->get_param('festival_id') ?: Peanut_Festival_Settings::get_active_festival_id();
        $result = Peanut_Festival_Mailchimp::sync_attendees($festival_id);
        return new \WP_REST_Response($result);
    }

    public function sync_all_to_mailchimp(\WP_REST_Request $request): \WP_REST_Response {
        $festival_id = (int) $request->get_param('festival_id') ?: Peanut_Festival_Settings::get_active_festival_id();
        $result = Peanut_Festival_Mailchimp::sync_all($festival_id);
        return new \WP_REST_Response($result);
    }

    // Check-in
    public function verify_and_checkin(\WP_REST_Request $request): \WP_REST_Response {
        $ticket_code = strtoupper(sanitize_text_field($request->get_param('ticket_code')));
        $show_id = (int) $request->get_param('show_id');

        if (empty($ticket_code)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Ticket code is required',
            ], 400);
        }

        // Look up ticket
        $ticket = Peanut_Festival_Database::get_row('tickets', ['ticket_code' => $ticket_code]);

        if (!$ticket) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        // Check if ticket matches show (if show specified)
        if ($show_id && (int) $ticket->show_id !== $show_id) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Ticket is for a different show',
            ], 400);
        }

        // Check payment status
        if ($ticket->payment_status !== 'completed') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Ticket payment not completed (status: ' . $ticket->payment_status . ')',
            ], 400);
        }

        // Check if already checked in
        if ($ticket->checked_in) {
            $checked_in_time = $ticket->checked_in_at ? date('g:i A', strtotime($ticket->checked_in_at)) : 'earlier';
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Already checked in at ' . $checked_in_time,
            ], 400);
        }

        // Perform check-in
        $result = Peanut_Festival_Attendees::check_in_ticket($ticket->id);

        if ($result === false) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to check in ticket',
            ], 500);
        }

        // Get attendee info
        $attendee = Peanut_Festival_Attendees::get_by_id($ticket->attendee_id);
        $name = $attendee ? $attendee->name : 'Unknown';

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Checked in successfully',
            'data' => [
                'name' => $name,
                'quantity' => (int) $ticket->quantity,
                'ticket_code' => $ticket_code,
            ],
        ]);
    }

    public function lookup_ticket(\WP_REST_Request $request): \WP_REST_Response {
        $ticket_code = strtoupper(sanitize_text_field($request->get_param('ticket_code')));

        if (empty($ticket_code)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Ticket code is required',
            ], 400);
        }

        $ticket = Peanut_Festival_Database::get_row('tickets', ['ticket_code' => $ticket_code]);

        if (!$ticket) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        $attendee = Peanut_Festival_Attendees::get_by_id($ticket->attendee_id);
        $show = Peanut_Festival_Shows::get_by_id($ticket->show_id);

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'ticket_code' => $ticket_code,
                'name' => $attendee ? $attendee->name : 'Unknown',
                'email' => $attendee ? $attendee->email : '',
                'quantity' => (int) $ticket->quantity,
                'show' => $show ? $show->title : 'Unknown',
                'show_date' => $show ? $show->show_date : '',
                'payment_status' => $ticket->payment_status,
                'checked_in' => (bool) $ticket->checked_in,
                'checked_in_at' => $ticket->checked_in_at,
            ],
        ]);
    }

}
