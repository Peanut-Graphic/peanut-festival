<?php
/**
 * Plugin activation handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class Peanut_Festival_Activator {

    public static function activate(): void {
        self::create_tables();
        self::create_roles();
        self::create_options();
        self::create_pages();

        flush_rewrite_rules();

        update_option('peanut_festival_version', PEANUT_FESTIVAL_VERSION);
        update_option('peanut_festival_db_version', '1.0.0');
    }

    private static function create_tables(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Festivals table
        $table_festivals = $wpdb->prefix . 'pf_festivals';
        $sql_festivals = "CREATE TABLE $table_festivals (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            start_date date DEFAULT NULL,
            end_date date DEFAULT NULL,
            location varchar(255) DEFAULT NULL,
            status varchar(20) DEFAULT 'draft',
            settings longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY dates (start_date, end_date)
        ) $charset_collate;";
        dbDelta($sql_festivals);

        // Shows table
        $table_shows = $wpdb->prefix . 'pf_shows';
        $sql_shows = "CREATE TABLE $table_shows (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            festival_id bigint(20) unsigned NOT NULL,
            eventbrite_id varchar(50) DEFAULT NULL,
            title varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            venue_id bigint(20) unsigned DEFAULT NULL,
            show_date date NOT NULL,
            start_time time DEFAULT NULL,
            end_time time DEFAULT NULL,
            capacity int(11) DEFAULT NULL,
            ticket_price decimal(10,2) DEFAULT NULL,
            status varchar(20) DEFAULT 'draft',
            completed_at datetime DEFAULT NULL,
            featured tinyint(1) DEFAULT 0,
            kid_friendly tinyint(1) DEFAULT 0,
            voting_config longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY festival_id (festival_id),
            KEY venue_id (venue_id),
            KEY show_date (show_date),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_shows);

        // Performers table
        $table_performers = $wpdb->prefix . 'pf_performers';
        $sql_performers = "CREATE TABLE $table_performers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT NULL,
            festival_id bigint(20) unsigned DEFAULT NULL,
            name varchar(255) NOT NULL,
            email varchar(255) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            bio text,
            photo_url varchar(500) DEFAULT NULL,
            website varchar(500) DEFAULT NULL,
            social_links longtext,
            performance_type varchar(100) DEFAULT NULL,
            technical_requirements text,
            compensation decimal(10,2) DEFAULT NULL,
            travel_covered tinyint(1) DEFAULT 0,
            lodging_covered tinyint(1) DEFAULT 0,
            application_status varchar(20) DEFAULT 'pending',
            application_date datetime DEFAULT NULL,
            review_notes text,
            reviewed_by bigint(20) unsigned DEFAULT NULL,
            reviewed_at datetime DEFAULT NULL,
            notification_sent tinyint(1) DEFAULT 0,
            rating_internal decimal(3,2) DEFAULT NULL,
            pros text,
            cons text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY festival_id (festival_id),
            KEY application_status (application_status),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_performers);

        // Show-Performer assignments
        $table_show_performers = $wpdb->prefix . 'pf_show_performers';
        $sql_show_performers = "CREATE TABLE $table_show_performers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            show_id bigint(20) unsigned NOT NULL,
            performer_id bigint(20) unsigned NOT NULL,
            slot_order int(11) DEFAULT 0,
            set_length_minutes int(11) DEFAULT NULL,
            performance_time time DEFAULT NULL,
            confirmed tinyint(1) DEFAULT 0,
            notes text,
            PRIMARY KEY (id),
            UNIQUE KEY unique_show_performer (show_id, performer_id),
            KEY show_id (show_id),
            KEY performer_id (performer_id)
        ) $charset_collate;";
        dbDelta($sql_show_performers);

        // Venues table
        $table_venues = $wpdb->prefix . 'pf_venues';
        $sql_venues = "CREATE TABLE $table_venues (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            festival_id bigint(20) unsigned DEFAULT NULL,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            address varchar(500) DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            state varchar(50) DEFAULT NULL,
            zip varchar(20) DEFAULT NULL,
            capacity int(11) DEFAULT NULL,
            venue_type varchar(50) DEFAULT 'other',
            amenities longtext,
            contact_name varchar(255) DEFAULT NULL,
            contact_email varchar(255) DEFAULT NULL,
            contact_phone varchar(50) DEFAULT NULL,
            rental_cost decimal(10,2) DEFAULT NULL,
            revenue_share decimal(5,2) DEFAULT NULL,
            tech_specs text,
            pros text,
            cons text,
            rating_internal decimal(3,2) DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY festival_id (festival_id),
            KEY venue_type (venue_type),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_venues);

        // Votes table
        $table_votes = $wpdb->prefix . 'pf_votes';
        $sql_votes = "CREATE TABLE $table_votes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            show_slug varchar(200) NOT NULL,
            group_name varchar(50) NOT NULL,
            performer_id bigint(20) unsigned NOT NULL,
            vote_rank tinyint(2) DEFAULT 1,
            ip_hash varchar(64) NOT NULL,
            ua_hash varchar(64) NOT NULL,
            token varchar(64) NOT NULL,
            voted_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY show_group (show_slug, group_name),
            KEY show_performer (show_slug, performer_id),
            KEY ip_hash (ip_hash),
            KEY token (token)
        ) $charset_collate;";
        dbDelta($sql_votes);

        // Voting finals table
        $table_voting_finals = $wpdb->prefix . 'pf_voting_finals';
        $sql_voting_finals = "CREATE TABLE $table_voting_finals (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            show_slug varchar(200) NOT NULL,
            performer_id bigint(20) unsigned NOT NULL,
            group_name varchar(50) NOT NULL,
            raw_score decimal(10,2) DEFAULT 0,
            normalized_score decimal(10,2) DEFAULT 0,
            final_rank int(11) DEFAULT 0,
            first_place_votes int(11) DEFAULT 0,
            second_place_votes int(11) DEFAULT 0,
            total_votes int(11) DEFAULT 0,
            calculated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY show_slug (show_slug),
            KEY final_rank (final_rank)
        ) $charset_collate;";
        dbDelta($sql_voting_finals);

        // Volunteers table
        $table_volunteers = $wpdb->prefix . 'pf_volunteers';
        $sql_volunteers = "CREATE TABLE $table_volunteers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT NULL,
            festival_id bigint(20) unsigned NOT NULL,
            name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(50) DEFAULT NULL,
            emergency_contact varchar(255) DEFAULT NULL,
            emergency_phone varchar(50) DEFAULT NULL,
            skills longtext,
            availability longtext,
            shirt_size varchar(10) DEFAULT NULL,
            dietary_restrictions text,
            status varchar(20) DEFAULT 'applied',
            notes text,
            hours_completed decimal(5,2) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY festival_id (festival_id),
            KEY status (status),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_volunteers);

        // Volunteer shifts table
        $table_volunteer_shifts = $wpdb->prefix . 'pf_volunteer_shifts';
        $sql_volunteer_shifts = "CREATE TABLE $table_volunteer_shifts (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            festival_id bigint(20) unsigned NOT NULL,
            task_name varchar(255) NOT NULL,
            description text,
            location varchar(255) DEFAULT NULL,
            shift_date date NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            slots_total int(11) DEFAULT 1,
            slots_filled int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'open',
            PRIMARY KEY (id),
            KEY festival_id (festival_id),
            KEY shift_date (shift_date),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_volunteer_shifts);

        // Volunteer shift assignments
        $table_volunteer_assignments = $wpdb->prefix . 'pf_volunteer_assignments';
        $sql_volunteer_assignments = "CREATE TABLE $table_volunteer_assignments (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            shift_id bigint(20) unsigned NOT NULL,
            volunteer_id bigint(20) unsigned NOT NULL,
            checked_in tinyint(1) DEFAULT 0,
            checked_in_at datetime DEFAULT NULL,
            checked_out_at datetime DEFAULT NULL,
            hours_worked decimal(4,2) DEFAULT NULL,
            notes text,
            PRIMARY KEY (id),
            UNIQUE KEY unique_assignment (shift_id, volunteer_id),
            KEY volunteer_id (volunteer_id)
        ) $charset_collate;";
        dbDelta($sql_volunteer_assignments);

        // Attendees table
        $table_attendees = $wpdb->prefix . 'pf_attendees';
        $sql_attendees = "CREATE TABLE $table_attendees (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT NULL,
            festival_id bigint(20) unsigned NOT NULL,
            email varchar(255) NOT NULL,
            name varchar(255) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            preferences longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY festival_id (festival_id),
            KEY email (email),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_attendees);

        // Tickets table
        $table_tickets = $wpdb->prefix . 'pf_tickets';
        $sql_tickets = "CREATE TABLE $table_tickets (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            attendee_id bigint(20) unsigned NOT NULL,
            show_id bigint(20) unsigned NOT NULL,
            eventbrite_order_id varchar(100) DEFAULT NULL,
            ticket_type varchar(50) DEFAULT NULL,
            quantity int(11) DEFAULT 1,
            total_paid decimal(10,2) DEFAULT NULL,
            purchase_date datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'purchased',
            qr_code varchar(255) DEFAULT NULL,
            checked_in tinyint(1) DEFAULT 0,
            checked_in_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY attendee_id (attendee_id),
            KEY show_id (show_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_tickets);

        // Coupons table
        $table_coupons = $wpdb->prefix . 'pf_coupons';
        $sql_coupons = "CREATE TABLE $table_coupons (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            festival_id bigint(20) unsigned NOT NULL,
            vendor_id bigint(20) unsigned DEFAULT NULL,
            code varchar(50) NOT NULL,
            description text,
            discount_type varchar(20) DEFAULT 'percentage',
            discount_value decimal(10,2) DEFAULT NULL,
            valid_from date DEFAULT NULL,
            valid_until date DEFAULT NULL,
            max_uses int(11) DEFAULT NULL,
            times_used int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY festival_id (festival_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_coupons);

        // Vendors table
        $table_vendors = $wpdb->prefix . 'pf_vendors';
        $sql_vendors = "CREATE TABLE $table_vendors (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            festival_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            business_name varchar(255) NOT NULL,
            contact_name varchar(255) DEFAULT NULL,
            email varchar(255) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            vendor_type varchar(50) DEFAULT 'other',
            description text,
            products text,
            booth_requirements text,
            electricity_needed tinyint(1) DEFAULT 0,
            booth_fee decimal(10,2) DEFAULT NULL,
            fee_paid tinyint(1) DEFAULT 0,
            booth_location varchar(100) DEFAULT NULL,
            insurance_verified tinyint(1) DEFAULT 0,
            license_verified tinyint(1) DEFAULT 0,
            status varchar(20) DEFAULT 'applied',
            rating_internal decimal(3,2) DEFAULT NULL,
            pros text,
            cons text,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY festival_id (festival_id),
            KEY vendor_type (vendor_type),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_vendors);

        // Sponsors table
        $table_sponsors = $wpdb->prefix . 'pf_sponsors';
        $sql_sponsors = "CREATE TABLE $table_sponsors (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            festival_id bigint(20) unsigned NOT NULL,
            company_name varchar(255) NOT NULL,
            contact_name varchar(255) DEFAULT NULL,
            email varchar(255) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            tier varchar(50) DEFAULT 'bronze',
            sponsorship_amount decimal(10,2) DEFAULT NULL,
            in_kind_value decimal(10,2) DEFAULT NULL,
            in_kind_description text,
            benefits longtext,
            logo_url varchar(500) DEFAULT NULL,
            website varchar(500) DEFAULT NULL,
            social_links longtext,
            contract_signed tinyint(1) DEFAULT 0,
            payment_received tinyint(1) DEFAULT 0,
            status varchar(20) DEFAULT 'prospect',
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY festival_id (festival_id),
            KEY tier (tier),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_sponsors);

        // Messages table
        $table_messages = $wpdb->prefix . 'pf_messages';
        $sql_messages = "CREATE TABLE $table_messages (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            festival_id bigint(20) unsigned NOT NULL,
            conversation_id varchar(100) NOT NULL,
            sender_id bigint(20) unsigned NOT NULL,
            sender_type varchar(20) NOT NULL,
            recipient_id bigint(20) unsigned DEFAULT NULL,
            recipient_type varchar(20) DEFAULT NULL,
            subject varchar(255) DEFAULT NULL,
            content text NOT NULL,
            is_read tinyint(1) DEFAULT 0,
            is_broadcast tinyint(1) DEFAULT 0,
            broadcast_group varchar(50) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY sender (sender_id, sender_type),
            KEY recipient (recipient_id, recipient_type)
        ) $charset_collate;";
        dbDelta($sql_messages);

        // Transactions table
        $table_transactions = $wpdb->prefix . 'pf_transactions';
        $sql_transactions = "CREATE TABLE $table_transactions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            festival_id bigint(20) unsigned NOT NULL,
            transaction_type varchar(20) NOT NULL,
            category varchar(100) NOT NULL,
            amount decimal(10,2) NOT NULL,
            description text,
            reference_type varchar(50) DEFAULT NULL,
            reference_id bigint(20) unsigned DEFAULT NULL,
            payment_method varchar(50) DEFAULT NULL,
            transaction_date date NOT NULL,
            recorded_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY festival_id (festival_id),
            KEY transaction_type (transaction_type),
            KEY category (category),
            KEY transaction_date (transaction_date),
            KEY festival_created (festival_id, created_at)
        ) $charset_collate;";
        dbDelta($sql_transactions);

        // Flyer templates table
        $table_flyer_templates = $wpdb->prefix . 'pf_flyer_templates';
        $sql_flyer_templates = "CREATE TABLE $table_flyer_templates (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            festival_id bigint(20) unsigned DEFAULT NULL,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            template_url varchar(500) DEFAULT NULL,
            mask_url varchar(500) DEFAULT NULL,
            frame longtext,
            namebox longtext,
            title varchar(255) DEFAULT NULL,
            subtitle varchar(255) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY festival_id (festival_id)
        ) $charset_collate;";
        dbDelta($sql_flyer_templates);

        // Flyer usage log table
        $table_flyer_usage = $wpdb->prefix . 'pf_flyer_usage';
        $sql_flyer_usage = "CREATE TABLE $table_flyer_usage (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            template_id bigint(20) unsigned DEFAULT NULL,
            performer_name varchar(255) DEFAULT NULL,
            image_url varchar(500) DEFAULT NULL,
            thumb_url varchar(500) DEFAULT NULL,
            page_url varchar(500) DEFAULT NULL,
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY template_id (template_id)
        ) $charset_collate;";
        dbDelta($sql_flyer_usage);

        // Activity log table
        $table_activity_log = $wpdb->prefix . 'pf_activity_log';
        $sql_activity_log = "CREATE TABLE $table_activity_log (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            festival_id bigint(20) unsigned DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            action varchar(100) NOT NULL,
            entity_type varchar(50) DEFAULT NULL,
            entity_id bigint(20) unsigned DEFAULT NULL,
            details longtext,
            ip_address varchar(45) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY festival_id (festival_id),
            KEY user_id (user_id),
            KEY action (action),
            KEY entity (entity_type, entity_id)
        ) $charset_collate;";
        dbDelta($sql_activity_log);

        // Issues table
        $table_issues = $wpdb->prefix . 'pf_issues';
        $sql_issues = "CREATE TABLE $table_issues (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            festival_id bigint(20) unsigned NOT NULL,
            reporter_id bigint(20) unsigned DEFAULT NULL,
            reporter_type varchar(20) DEFAULT NULL,
            entity_type varchar(50) DEFAULT NULL,
            entity_id bigint(20) unsigned DEFAULT NULL,
            issue_type varchar(20) DEFAULT NULL,
            severity varchar(20) DEFAULT 'medium',
            title varchar(255) NOT NULL,
            description text,
            status varchar(20) DEFAULT 'open',
            resolution text,
            resolved_by bigint(20) unsigned DEFAULT NULL,
            resolved_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY festival_id (festival_id),
            KEY entity (entity_type, entity_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_issues);
    }

    private static function create_roles(): void {
        // Producer role (full admin)
        add_role('pf_producer', __('Festival Producer', 'peanut-festival'), [
            'read' => true,
            'manage_pf_festival' => true,
            'manage_pf_shows' => true,
            'manage_pf_performers' => true,
            'manage_pf_venues' => true,
            'manage_pf_volunteers' => true,
            'manage_pf_vendors' => true,
            'manage_pf_sponsors' => true,
            'manage_pf_financials' => true,
            'manage_pf_settings' => true,
        ]);

        // Venue Manager role
        add_role('pf_venue_manager', __('Venue Manager', 'peanut-festival'), [
            'read' => true,
            'manage_pf_venues' => true,
        ]);

        // Volunteer Coordinator role
        add_role('pf_volunteer_coordinator', __('Volunteer Coordinator', 'peanut-festival'), [
            'read' => true,
            'manage_pf_volunteers' => true,
        ]);

        // Box Office role
        add_role('pf_box_office', __('Box Office', 'peanut-festival'), [
            'read' => true,
            'manage_pf_attendees' => true,
        ]);

        // Add capabilities to administrator
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('manage_pf_festival');
            $admin->add_cap('manage_pf_shows');
            $admin->add_cap('manage_pf_performers');
            $admin->add_cap('manage_pf_venues');
            $admin->add_cap('manage_pf_volunteers');
            $admin->add_cap('manage_pf_vendors');
            $admin->add_cap('manage_pf_sponsors');
            $admin->add_cap('manage_pf_financials');
            $admin->add_cap('manage_pf_settings');
            $admin->add_cap('manage_pf_attendees');
        }
    }

    private static function create_options(): void {
        $default_settings = [
            'active_festival_id' => null,
            'eventbrite_token' => '',
            'eventbrite_org_id' => '',
            'mailchimp_api_key' => '',
            'mailchimp_list_id' => '',
            'voting_weight_first' => 3,
            'voting_weight_second' => 2,
            'voting_weight_third' => 1,
            'notification_email' => get_option('admin_email'),
        ];

        add_option('peanut_festival_settings', $default_settings);
    }

    private static function create_pages(): void {
        // Create default pages if they don't exist
        $pages = [
            'performer-application' => [
                'title' => 'Apply to Perform',
                'content' => '[pf_performer_apply]',
            ],
            'volunteer-signup' => [
                'title' => 'Volunteer Signup',
                'content' => '[pf_volunteer_signup]',
            ],
            'festival-schedule' => [
                'title' => 'Festival Schedule',
                'content' => '[pf_schedule]',
            ],
            'vote' => [
                'title' => 'Vote',
                'content' => '[pf_vote]',
            ],
            'create-your-flyer' => [
                'title' => 'Create Your Flyer',
                'content' => '[pf_flyer]',
            ],
        ];

        foreach ($pages as $slug => $page) {
            $existing = get_page_by_path($slug);
            if (!$existing) {
                wp_insert_post([
                    'post_title' => $page['title'],
                    'post_name' => $slug,
                    'post_content' => $page['content'],
                    'post_status' => 'draft',
                    'post_type' => 'page',
                ]);
            }
        }
    }
}
