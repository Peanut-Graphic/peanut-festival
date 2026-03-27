<?php
/**
 * Plugin Name: Peanut Festival
 * Plugin URI: https://peanut.graphics/festival
 * Description: Comprehensive festival organization platform for producers, performers, venues, volunteers, vendors, sponsors, and attendees.
 * Version: 1.3.1
 * Author: Peanut Graphics
 * Author URI: https://peanut.graphics
 * License: GPL v2 or later
 * Text Domain: peanut-festival
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('PEANUT_FESTIVAL_VERSION', '1.3.1');
define('PEANUT_FESTIVAL_PATH', plugin_dir_path(__FILE__));
define('PEANUT_FESTIVAL_URL', plugin_dir_url(__FILE__));
define('PEANUT_FESTIVAL_BASENAME', plugin_basename(__FILE__));

// Load activator/deactivator early (needed for activation hooks)
require_once PEANUT_FESTIVAL_PATH . 'includes/class-activator.php';
require_once PEANUT_FESTIVAL_PATH . 'includes/class-deactivator.php';

/**
 * Main plugin class
 */
final class Peanut_Festival {

    private static ?Peanut_Festival $instance = null;

    public static function get_instance(): Peanut_Festival {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_api_hooks();
    }

    private function load_dependencies(): void {
        // Core classes (activator/deactivator loaded early above)
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-database.php';
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-settings.php';

        // Module classes
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-festivals.php';
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-shows.php';
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-performers.php';
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-venues.php';
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-voting.php';
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-volunteers.php';
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-attendees.php';
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-vendors.php';
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-sponsors.php';
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-messaging.php';
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-transactions.php';
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-analytics.php';
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-flyer-generator.php';

        // Integration classes
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-eventbrite.php';
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-mailchimp.php';
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-notifications.php';
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-payments.php';
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-booker-integration.php';
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-competitions.php';

        // Firebase real-time integration (Phase 3)
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-firebase.php';
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-realtime-sync.php';

        // Security classes
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-rate-limiter.php';

        // Caching
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-cache.php';

        // Logging
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-logger.php';

        // ML Integration
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-ml-festival-predictor.php';

        // Migrations
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-migrations.php';

        // API classes
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-rest-response.php';
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-rest-api.php';
        require_once PEANUT_FESTIVAL_PATH . 'includes/class-rest-api-admin.php';

        // Admin classes
        require_once PEANUT_FESTIVAL_PATH . 'admin/class-admin-pages.php';

        // Public classes
        require_once PEANUT_FESTIVAL_PATH . 'public/class-public.php';
    }

    private function set_locale(): void {
        add_action('plugins_loaded', function() {
            load_plugin_textdomain(
                'peanut-festival',
                false,
                dirname(PEANUT_FESTIVAL_BASENAME) . '/languages/'
            );
        });
    }

    private function define_admin_hooks(): void {
        $admin = new Peanut_Festival_Admin_Pages();
        add_action('admin_menu', [$admin, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$admin, 'enqueue_scripts']);
    }

    private function define_public_hooks(): void {
        $public = new Peanut_Festival_Public();
        add_action('wp_enqueue_scripts', [$public, 'enqueue_scripts']);
        add_action('init', [$public, 'register_shortcodes']);
    }

    private function define_api_hooks(): void {
        add_action('rest_api_init', function() {
            $public_api = new Peanut_Festival_REST_API();
            $public_api->register_routes();

            $admin_api = new Peanut_Festival_REST_API_Admin();
            $admin_api->register_routes();

            // Phase 3: Firebase REST routes (always register config endpoint)
            Peanut_Festival_Firebase::register_routes();
        });
    }

    public function run(): void {
        // Run database migrations if needed
        if (Peanut_Festival_Migrations::needs_migration()) {
            $result = Peanut_Festival_Migrations::run();
            if (!$result['success']) {
                error_log('Peanut Festival: Migration failed - ' . wp_json_encode($result));
            }
        }

        // Initialize modules
        Peanut_Festival_Festivals::get_instance();
        Peanut_Festival_Shows::get_instance();
        Peanut_Festival_Performers::get_instance();
        Peanut_Festival_Venues::get_instance();
        Peanut_Festival_Voting::get_instance();
        Peanut_Festival_Volunteers::get_instance();
        Peanut_Festival_Attendees::get_instance();
        Peanut_Festival_Vendors::get_instance();
        Peanut_Festival_Sponsors::get_instance();
        Peanut_Festival_Messaging::get_instance();
        Peanut_Festival_Transactions::get_instance();
        Peanut_Festival_Analytics::get_instance();
        Peanut_Festival_Flyer_Generator::get_instance();
        Peanut_Festival_Eventbrite::get_instance();
        Peanut_Festival_Notifications::get_instance();
        Peanut_Festival_Booker_Integration::get_instance();
        Peanut_Festival_Competitions::get_instance();

        // ML Integration (predict attendance, optimize scheduling)
        Peanut_Festival_ML_Predictor::get_instance();

        // Phase 3: Firebase real-time sync
        if (Peanut_Festival_Firebase::is_enabled()) {
            Peanut_Festival_Firebase::get_instance();
            Peanut_Festival_Realtime_Sync::get_instance();
        }
    }
}

// Activation/Deactivation hooks
register_activation_hook(__FILE__, ['Peanut_Festival_Activator', 'activate']);
register_deactivation_hook(__FILE__, function() {
    // Clear ML training schedule
    Peanut_Festival_ML_Predictor::clear_scheduled_training();

    // Call standard deactivator
    Peanut_Festival_Deactivator::deactivate();
});

// Add custom cron intervals
add_filter('cron_schedules', function($schedules) {
    if (!isset($schedules['five_minutes'])) {
        $schedules['five_minutes'] = [
            'interval' => 300,
            'display' => __('Every Five Minutes', 'peanut-festival'),
        ];
    }
    return $schedules;
});

// Initialize plugin
function peanut_festival_init(): Peanut_Festival {
    $plugin = Peanut_Festival::get_instance();
    $plugin->run();
    return $plugin;
}
add_action('plugins_loaded', 'peanut_festival_init');
