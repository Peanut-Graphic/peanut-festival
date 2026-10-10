<?php
/**
 * Payment processing class - Stripe integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Peanut_Festival_Payments {

    private static ?Peanut_Festival_Payments $instance = null;
    private static ?string $stripe_secret_key = null;
    private static ?string $stripe_publishable_key = null;
    private static bool $test_mode = true;

    public static function get_instance(): Peanut_Festival_Payments {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Track if we've shown the env var warning
     */
    private static bool $env_warning_shown = false;

    private function __construct() {
        self::load_settings();
        add_action('admin_notices', [__CLASS__, 'maybe_show_env_warning']);
    }

    /**
     * Load Stripe settings from environment variables first, then fall back to wp_options.
     *
     * SECURITY: Environment variables are preferred over database storage for sensitive keys.
     * Set these in wp-config.php or server environment:
     * - PEANUT_STRIPE_TEST_SECRET_KEY
     * - PEANUT_STRIPE_TEST_PUBLISHABLE_KEY
     * - PEANUT_STRIPE_LIVE_SECRET_KEY
     * - PEANUT_STRIPE_LIVE_PUBLISHABLE_KEY
     * - PEANUT_STRIPE_TEST_MODE (set to '1' or 'true' for test mode)
     */
    private static function load_settings(): void {
        // Check environment for test mode setting
        $env_test_mode = getenv('PEANUT_STRIPE_TEST_MODE');
        if ($env_test_mode !== false) {
            self::$test_mode = in_array(strtolower($env_test_mode), ['1', 'true', 'yes'], true);
        } else {
            $settings = Peanut_Festival_Settings::get();
            self::$test_mode = !empty($settings['stripe_test_mode']);
        }

        // Load keys from environment first, fall back to database
        if (self::$test_mode) {
            self::$stripe_secret_key = self::get_key_from_env_or_settings(
                'PEANUT_STRIPE_TEST_SECRET_KEY',
                'stripe_test_secret_key'
            );
            self::$stripe_publishable_key = self::get_key_from_env_or_settings(
                'PEANUT_STRIPE_TEST_PUBLISHABLE_KEY',
                'stripe_test_publishable_key'
            );
        } else {
            self::$stripe_secret_key = self::get_key_from_env_or_settings(
                'PEANUT_STRIPE_LIVE_SECRET_KEY',
                'stripe_live_secret_key'
            );
            self::$stripe_publishable_key = self::get_key_from_env_or_settings(
                'PEANUT_STRIPE_LIVE_PUBLISHABLE_KEY',
                'stripe_live_publishable_key'
            );
        }
    }

    /**
     * Get a Stripe key from environment variable first, then fall back to settings.
     *
     * @param string $env_key Environment variable name.
     * @param string $settings_key Settings array key.
     * @return string The key value.
     */
    private static function get_key_from_env_or_settings(string $env_key, string $settings_key): string {
        // Try environment variable first (more secure)
        $env_value = getenv($env_key);
        if ($env_value !== false && !empty($env_value)) {
            return $env_value;
        }

        // Also check for constant definition in wp-config.php
        if (defined($env_key)) {
            return constant($env_key);
        }

        // Fall back to database settings (less secure)
        $settings = Peanut_Festival_Settings::get();
        return $settings[$settings_key] ?? '';
    }

    /**
     * Check if keys are loaded from environment (secure) or database (less secure).
     *
     * @return bool True if using environment variables.
     */
    public static function is_using_env_keys(): bool {
        if (self::$test_mode) {
            $env_key = getenv('PEANUT_STRIPE_TEST_SECRET_KEY');
            $const_key = defined('PEANUT_STRIPE_TEST_SECRET_KEY');
        } else {
            $env_key = getenv('PEANUT_STRIPE_LIVE_SECRET_KEY');
            $const_key = defined('PEANUT_STRIPE_LIVE_SECRET_KEY');
        }

        return ($env_key !== false && !empty($env_key)) || $const_key;
    }

    /**
     * Show admin warning if Stripe keys are stored in database instead of environment.
     */
    public static function maybe_show_env_warning(): void {
        // Only show on Peanut Festival admin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'peanut-festival') === false) {
            return;
        }

        // Only show to administrators
        if (!current_user_can('manage_options')) {
            return;
        }

        // Don't show if not configured at all
        if (!self::is_configured()) {
            return;
        }

        // Don't show if already using environment variables
        if (self::is_using_env_keys()) {
            return;
        }

        // Only show once per page load
        if (self::$env_warning_shown) {
            return;
        }
        self::$env_warning_shown = true;

        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>Peanut Festival Security Recommendation:</strong> ';
        echo 'Stripe API keys are currently stored in the database. ';
        echo 'For better security, add them to your <code>wp-config.php</code> file:</p>';
        echo '<pre style="background: #f1f1f1; padding: 10px; overflow-x: auto;">';
        echo "define('PEANUT_STRIPE_TEST_SECRET_KEY', 'sk_test_...');\n";
        echo "define('PEANUT_STRIPE_TEST_PUBLISHABLE_KEY', 'pk_test_...');\n";
        echo "define('PEANUT_STRIPE_LIVE_SECRET_KEY', 'sk_live_...');\n";
        echo "define('PEANUT_STRIPE_LIVE_PUBLISHABLE_KEY', 'pk_live_...');\n";
        echo "define('PEANUT_STRIPE_TEST_MODE', true); // or false for live";
        echo '</pre>';
        echo '</div>';
    }

    public static function get_publishable_key(): string {
        self::load_settings();
        return self::$stripe_publishable_key ?? '';
    }

    public static function is_configured(): bool {
        self::load_settings();
        return !empty(self::$stripe_secret_key) && !empty(self::$stripe_publishable_key);
    }

    public static function is_test_mode(): bool {
        self::load_settings();
        return self::$test_mode;
    }

    /**
     * Create a Stripe PaymentIntent for ticket purchase
     */
    public static function create_payment_intent(array $data): array {
        if (!self::is_configured()) {
            return ['success' => false, 'message' => 'Payment system not configured'];
        }

        $amount = (int) ($data['amount'] * 100); // Convert to cents
        $currency = $data['currency'] ?? 'usd';
        $metadata = $data['metadata'] ?? [];

        try {
            $response = self::stripe_request('POST', '/v1/payment_intents', [
                'amount' => $amount,
                'currency' => strtolower($currency),
                'automatic_payment_methods' => ['enabled' => 'true'],
                'metadata' => $metadata,
            ]);

            if (isset($response['error'])) {
                return [
                    'success' => false,
                    'message' => $response['error']['message'] ?? 'Payment intent creation failed',
                ];
            }

            return [
                'success' => true,
                'client_secret' => $response['client_secret'],
                'payment_intent_id' => $response['id'],
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Seconds to wait for another request that is fulfilling the same payment.
     */
    private const PAYMENT_LOCK_TIMEOUT = 5;

    /**
     * Whether a string looks like a Stripe PaymentIntent id (pi_...).
     *
     * The id is interpolated into a Stripe API path, so anything else (for
     * example "pi_x/../customers") must be rejected before any request is made.
     */
    public static function is_valid_payment_intent_id(string $payment_intent_id): bool {
        return (bool) preg_match('/^pi_[A-Za-z0-9]{1,250}$/', $payment_intent_id);
    }

    /**
     * Name of the MySQL advisory lock that serialises fulfilment of one payment.
     *
     * Scoped by table prefix so two sites sharing a MySQL server never contend,
     * and hashed to stay inside MySQL's 64-character lock-name limit.
     */
    public static function lock_name(string $payment_intent_id): string {
        global $wpdb;
        return 'pf_tkt_' . md5($wpdb->prefix . '|' . $payment_intent_id);
    }

    /**
     * Confirm a PaymentIntent succeeded and return its ticket.
     *
     * Idempotent: a PaymentIntent maps to exactly one ticket. Replaying the
     * confirm call (or racing it against the Stripe webhook) returns the ticket
     * that already exists instead of minting another one.
     */
    public static function confirm_payment(string $payment_intent_id): array {
        if (!self::is_valid_payment_intent_id($payment_intent_id)) {
            return [
                'success' => false,
                'code' => 'invalid_payment_intent',
                'message' => 'Invalid payment intent ID',
            ];
        }

        if (!self::is_configured()) {
            return ['success' => false, 'message' => 'Payment system not configured'];
        }

        try {
            $response = self::stripe_request('GET', '/v1/payment_intents/' . rawurlencode($payment_intent_id));

            if (isset($response['error'])) {
                return [
                    'success' => false,
                    'message' => $response['error']['message'] ?? 'Payment verification failed',
                ];
            }

            if (($response['id'] ?? '') !== $payment_intent_id) {
                return [
                    'success' => false,
                    'message' => 'Payment verification failed',
                ];
            }

            if (($response['status'] ?? '') !== 'succeeded') {
                return [
                    'success' => false,
                    'message' => 'Payment not yet completed',
                    'status' => $response['status'] ?? null,
                ];
            }

            $fulfilment = self::fulfill_payment($response);

            if ($fulfilment['status'] === 'busy') {
                return [
                    'success' => false,
                    'code' => 'payment_processing',
                    'message' => 'This payment is already being processed. Please try again in a moment.',
                ];
            }

            if ($fulfilment['ticket'] === null) {
                return [
                    'success' => false,
                    'message' => 'Payment succeeded but ticket creation failed',
                ];
            }

            return [
                'success' => true,
                'ticket_id' => (int) $fulfilment['ticket']['id'],
                'ticket_code' => (string) $fulfilment['ticket']['ticket_code'],
                'existing' => $fulfilment['status'] === 'existing',
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Find the ticket already issued for a payment, if any.
     *
     * @return array{id: int, ticket_code: string}|null
     */
    private static function find_ticket_for_payment(string $payment_intent_id): ?array {
        $existing = Peanut_Festival_Database::get_row('tickets', ['payment_id' => $payment_intent_id]);

        if (!$existing) {
            return null;
        }

        return [
            'id' => (int) $existing->id,
            'ticket_code' => (string) $existing->ticket_code,
        ];
    }

    /**
     * Issue the ticket for a succeeded PaymentIntent exactly once.
     *
     * Three layers make this safe under replay and concurrency:
     *  1. an existing ticket for the payment is returned as-is;
     *  2. a per-payment MySQL advisory lock serialises the check-then-create
     *     (confirm endpoint, webhook, retries, multiple PHP workers);
     *  3. the UNIQUE index on tickets.payment_id rejects any second insert
     *     that still slips through, in which case the winner's ticket is returned.
     *
     * @param array $payment_intent Stripe PaymentIntent object (decoded).
     * @return array{status: string, ticket: ?array} status: existing|created|busy|failed.
     */
    private static function fulfill_payment(array $payment_intent): array {
        global $wpdb;

        $payment_intent_id = (string) ($payment_intent['id'] ?? '');

        $existing = self::find_ticket_for_payment($payment_intent_id);
        if ($existing) {
            return ['status' => 'existing', 'ticket' => $existing];
        }

        $lock = self::lock_name($payment_intent_id);
        $timeout = (int) apply_filters('peanut_festival_payment_lock_timeout', self::PAYMENT_LOCK_TIMEOUT);
        $acquired = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock, max(0, $timeout)));

        if ((string) $acquired !== '1') {
            // Another request is fulfilling this payment right now. It may have
            // just finished; otherwise tell the caller to retry.
            $existing = self::find_ticket_for_payment($payment_intent_id);
            if ($existing) {
                return ['status' => 'existing', 'ticket' => $existing];
            }
            return ['status' => 'busy', 'ticket' => null];
        }

        try {
            $existing = self::find_ticket_for_payment($payment_intent_id);
            if ($existing) {
                return ['status' => 'existing', 'ticket' => $existing];
            }

            $ticket = self::create_ticket_from_payment($payment_intent, $payment_intent['metadata'] ?? []);

            if ($ticket) {
                return ['status' => 'created', 'ticket' => $ticket];
            }

            // The insert may have lost a race to a writer that bypassed the lock
            // (UNIQUE index violation): hand back the ticket that won.
            $existing = self::find_ticket_for_payment($payment_intent_id);
            if ($existing) {
                return ['status' => 'existing', 'ticket' => $existing];
            }

            return ['status' => 'failed', 'ticket' => null];
        } finally {
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }

    /**
     * Create a ticket record after successful payment
     */
    private static function create_ticket_from_payment(array $payment, array $metadata): ?array {
        $show_id = (int) ($metadata['show_id'] ?? 0);
        $quantity = (int) ($metadata['quantity'] ?? 1);

        if (!$show_id) {
            return null;
        }

        // Create or find attendee
        $attendee_id = self::get_or_create_attendee([
            'name' => $metadata['customer_name'] ?? '',
            'email' => $metadata['customer_email'] ?? '',
            'phone' => $metadata['customer_phone'] ?? '',
            'festival_id' => (int) ($metadata['festival_id'] ?? 0),
        ]);

        if (!$attendee_id) {
            return null;
        }

        // Generate ticket code
        $ticket_code = strtoupper(substr(md5($payment['id'] . time()), 0, 8));

        // Create ticket record
        $ticket_data = [
            'attendee_id' => $attendee_id,
            'show_id' => $show_id,
            'quantity' => $quantity,
            'ticket_code' => $ticket_code,
            'payment_id' => $payment['id'],
            'payment_amount' => $payment['amount'] / 100, // Convert from cents
            'payment_status' => 'completed',
            'purchase_date' => current_time('mysql'),
        ];

        $ticket_id = Peanut_Festival_Database::insert('tickets', $ticket_data);

        if (!$ticket_id) {
            return null;
        }

        // Record transaction
        Peanut_Festival_Transactions::create([
            'festival_id' => (int) ($metadata['festival_id'] ?? 0),
            'transaction_type' => 'income',
            'category' => 'tickets',
            'amount' => $payment['amount'] / 100,
            'description' => 'Ticket purchase - ' . $ticket_code,
            'reference' => $payment['id'],
            'transaction_date' => current_time('mysql'),
        ]);

        // Send confirmation email
        self::send_ticket_confirmation($attendee_id, $ticket_id, $ticket_code);

        return [
            'id' => $ticket_id,
            'ticket_code' => $ticket_code,
        ];
    }

    /**
     * Get or create an attendee record
     */
    private static function get_or_create_attendee(array $data): ?int {
        if (empty($data['email'])) {
            return null;
        }

        // Check for existing attendee
        $existing = Peanut_Festival_Database::get_row('attendees', ['email' => $data['email']]);

        if ($existing) {
            return (int) $existing->id;
        }

        // Create new attendee
        $data['created_at'] = current_time('mysql');
        return Peanut_Festival_Database::insert('attendees', $data);
    }

    /**
     * Send ticket confirmation email
     */
    private static function send_ticket_confirmation(int $attendee_id, int $ticket_id, string $ticket_code): void {
        $attendee = Peanut_Festival_Database::get_row('attendees', ['id' => $attendee_id]);
        $ticket = Peanut_Festival_Database::get_row('tickets', ['id' => $ticket_id]);

        if (!$attendee || !$ticket || !$attendee->email) {
            return;
        }

        $show = Peanut_Festival_Shows::get_by_id($ticket->show_id);
        $show_title = $show ? $show->title : 'Unknown Show';
        $show_date = $show ? date('F j, Y', strtotime($show->show_date)) : '';
        $show_time = $show ? date('g:i A', strtotime($show->start_time)) : '';

        $subject = "Your Ticket Confirmation - {$show_title}";
        $message = sprintf(
            "<p>Hi %s,</p>
            <p>Thank you for your purchase! Here are your ticket details:</p>
            <div style='background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <p><strong>Show:</strong> %s</p>
                <p><strong>Date:</strong> %s</p>
                <p><strong>Time:</strong> %s</p>
                <p><strong>Quantity:</strong> %d</p>
                <p><strong>Ticket Code:</strong> <span style='font-size: 24px; font-weight: bold; color: #7c3aed;'>%s</span></p>
            </div>
            <p>Please present this ticket code at the venue for entry.</p>
            <p>See you at the show!</p>",
            esc_html($attendee->name),
            esc_html($show_title),
            $show_date,
            $show_time,
            (int) $ticket->quantity,
            esc_html($ticket_code)
        );

        Peanut_Festival_Notifications::send_email($attendee->email, $subject, $message);
    }

    /**
     * Handle Stripe webhook events
     */
    public static function handle_webhook(): void {
        $payload = file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $webhook_secret = Peanut_Festival_Settings::get('stripe_webhook_secret');

        if (!$webhook_secret) {
            http_response_code(400);
            echo json_encode(['error' => 'Webhook secret not configured']);
            exit;
        }

        try {
            // Verify webhook signature
            $event = self::verify_webhook_signature($payload, $sig_header, $webhook_secret);

            if (!$event) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid signature']);
                exit;
            }

            // Handle different event types
            switch ($event['type']) {
                case 'payment_intent.succeeded':
                    self::handle_payment_succeeded($event['data']['object']);
                    break;

                case 'payment_intent.payment_failed':
                    self::handle_payment_failed($event['data']['object']);
                    break;

                case 'charge.refunded':
                    self::handle_refund($event['data']['object']);
                    break;
            }

            http_response_code(200);
            echo json_encode(['received' => true]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Verify Stripe webhook signature
     */
    private static function verify_webhook_signature(string $payload, string $sig_header, string $secret): ?array {
        $elements = explode(',', $sig_header);
        $timestamp = null;
        $signatures = [];

        foreach ($elements as $element) {
            $parts = explode('=', $element, 2);
            if (count($parts) === 2) {
                if ($parts[0] === 't') {
                    $timestamp = $parts[1];
                } elseif ($parts[0] === 'v1') {
                    $signatures[] = $parts[1];
                }
            }
        }

        if (!$timestamp || empty($signatures)) {
            return null;
        }

        // Check timestamp is within tolerance (5 minutes)
        if (abs(time() - (int)$timestamp) > 300) {
            return null;
        }

        $signed_payload = $timestamp . '.' . $payload;
        $expected_signature = hash_hmac('sha256', $signed_payload, $secret);

        foreach ($signatures as $sig) {
            if (hash_equals($expected_signature, $sig)) {
                return json_decode($payload, true);
            }
        }

        return null;
    }

    /**
     * Handle successful payment webhook
     */
    private static function handle_payment_succeeded(array $payment_intent): void {
        if (!self::is_valid_payment_intent_id((string) ($payment_intent['id'] ?? ''))) {
            return;
        }

        // Shares the idempotent, locked fulfilment path with confirm_payment().
        self::fulfill_payment($payment_intent);
    }

    /**
     * Handle failed payment webhook
     */
    private static function handle_payment_failed(array $payment_intent): void {
        // Log the failure
        error_log('Peanut Festival: Payment failed - ' . $payment_intent['id']);

        // Update ticket if exists
        $ticket = Peanut_Festival_Database::get_row('tickets', ['payment_id' => $payment_intent['id']]);

        if ($ticket) {
            Peanut_Festival_Database::update('tickets', [
                'payment_status' => 'failed',
            ], ['id' => $ticket->id]);
        }
    }

    /**
     * Handle refund webhook
     */
    private static function handle_refund(array $charge): void {
        $payment_intent_id = $charge['payment_intent'] ?? null;

        if (!$payment_intent_id) {
            return;
        }

        $ticket = Peanut_Festival_Database::get_row('tickets', ['payment_id' => $payment_intent_id]);

        if (!$ticket) {
            return;
        }

        // Update ticket status
        Peanut_Festival_Database::update('tickets', [
            'payment_status' => 'refunded',
        ], ['id' => $ticket->id]);

        // Record refund transaction
        Peanut_Festival_Transactions::create([
            'festival_id' => $ticket->festival_id ?? 0,
            'transaction_type' => 'expense',
            'category' => 'refunds',
            'amount' => $charge['amount_refunded'] / 100,
            'description' => 'Ticket refund - ' . $ticket->ticket_code,
            'reference' => $charge['id'],
            'transaction_date' => current_time('mysql'),
        ]);
    }

    /**
     * Make a request to the Stripe API
     */
    private static function stripe_request(string $method, string $endpoint, array $data = []): array {
        $url = 'https://api.stripe.com' . $endpoint;

        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . self::$stripe_secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'timeout' => 30,
        ];

        if ($method === 'POST' && !empty($data)) {
            $args['body'] = http_build_query($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true) ?: [];
    }

    /**
     * Process a refund
     */
    public static function refund_ticket(int $ticket_id, string $reason = ''): array {
        if (!self::is_configured()) {
            return ['success' => false, 'message' => 'Payment system not configured'];
        }

        $ticket = Peanut_Festival_Database::get_row('tickets', ['id' => $ticket_id]);

        if (!$ticket || !$ticket->payment_id) {
            return ['success' => false, 'message' => 'Ticket not found or no payment associated'];
        }

        if ($ticket->payment_status === 'refunded') {
            return ['success' => false, 'message' => 'Ticket already refunded'];
        }

        try {
            $response = self::stripe_request('POST', '/v1/refunds', [
                'payment_intent' => $ticket->payment_id,
                'reason' => $reason ?: 'requested_by_customer',
            ]);

            if (isset($response['error'])) {
                return [
                    'success' => false,
                    'message' => $response['error']['message'] ?? 'Refund failed',
                ];
            }

            // Update ticket status
            Peanut_Festival_Database::update('tickets', [
                'payment_status' => 'refunded',
            ], ['id' => $ticket_id]);

            return ['success' => true, 'refund_id' => $response['id']];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get ticket sales summary for a show
     */
    public static function get_sales_summary(int $show_id): array {
        global $wpdb;
        $table = Peanut_Festival_Database::get_table_name('tickets');

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total_tickets,
                SUM(quantity) as total_quantity,
                SUM(payment_amount) as total_revenue,
                SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN payment_status = 'refunded' THEN 1 ELSE 0 END) as refunded,
                SUM(CASE WHEN checked_in = 1 THEN 1 ELSE 0 END) as checked_in
             FROM $table
             WHERE show_id = %d",
            $show_id
        ));

        return [
            'total_tickets' => (int) ($result->total_tickets ?? 0),
            'total_quantity' => (int) ($result->total_quantity ?? 0),
            'total_revenue' => (float) ($result->total_revenue ?? 0),
            'completed' => (int) ($result->completed ?? 0),
            'refunded' => (int) ($result->refunded ?? 0),
            'checked_in' => (int) ($result->checked_in ?? 0),
        ];
    }
}
