<?php
/**
 * Shows Management Class
 *
 * Handles all show/event-related operations including scheduling,
 * performer assignments, and venue management.
 *
 * @package    Peanut_Festival
 * @subpackage Includes
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Peanut_Festival_Shows
 *
 * Manages festival shows/events with their schedules, venues, and performer lineups.
 * Supports date range filtering and performer slot ordering.
 *
 * @since 1.0.0
 */
class Peanut_Festival_Shows {

    /**
     * Singleton instance.
     *
     * @since 1.0.0
     * @var Peanut_Festival_Shows|null
     */
    private static ?Peanut_Festival_Shows $instance = null;

    /**
     * Get singleton instance.
     *
     * @since 1.0.0
     * @return Peanut_Festival_Shows The singleton instance.
     */
    public static function get_instance(): Peanut_Festival_Shows {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton pattern.
     *
     * @since 1.0.0
     */
    private function __construct() {
        // Initialize hooks
    }

    /**
     * Get all shows with optional filtering and pagination.
     *
     * Includes venue information via JOIN.
     *
     * @since 1.0.0
     *
     * @param array $args {
     *     Optional. Arguments to filter and paginate results.
     *
     *     @type int|null $festival_id Filter by festival ID.
     *     @type int|null $venue_id    Filter by venue ID.
     *     @type string   $status      Filter by show status.
     *     @type string   $date_from   Filter shows on or after this date.
     *     @type string   $date_to     Filter shows on or before this date.
     *     @type string   $order_by    Column to sort by. Default 'show_date'.
     *     @type string   $order       Sort direction: 'ASC' or 'DESC'. Default 'ASC'.
     *     @type int      $limit       Maximum results to return. 0 for unlimited.
     *     @type int      $offset      Number of results to skip.
     * }
     * @return array Array of show objects with venue details.
     */
    public static function get_all(array $args = []): array {
        global $wpdb;
        $table = Peanut_Festival_Database::get_table_name('shows');
        $venues_table = Peanut_Festival_Database::get_table_name('venues');

        $defaults = [
            'festival_id' => null,
            'venue_id' => null,
            'status' => '',
            'date_from' => '',
            'date_to' => '',
            'order_by' => 'show_date',
            'order' => 'ASC',
            'limit' => 0,
            'offset' => 0,
        ];
        $args = wp_parse_args($args, $defaults);

        $sql = "SELECT s.*, v.name as venue_name, v.address as venue_address
                FROM $table s
                LEFT JOIN $venues_table v ON s.venue_id = v.id
                WHERE 1=1";
        $values = [];

        if ($args['festival_id']) {
            $sql .= " AND s.festival_id = %d";
            $values[] = $args['festival_id'];
        }

        if ($args['venue_id']) {
            $sql .= " AND s.venue_id = %d";
            $values[] = $args['venue_id'];
        }

        if ($args['status']) {
            if (is_array($args['status'])) {
                $placeholders = implode(',', array_fill(0, count($args['status']), '%s'));
                $sql .= " AND s.status IN ($placeholders)";
                $values = array_merge($values, $args['status']);
            } else {
                $sql .= " AND s.status = %s";
                $values[] = $args['status'];
            }
        }

        if ($args['date_from']) {
            $sql .= " AND s.show_date >= %s";
            $values[] = $args['date_from'];
        }

        if ($args['date_to']) {
            $sql .= " AND s.show_date <= %s";
            $values[] = $args['date_to'];
        }

        // Whitelist allowed columns for ORDER BY to prevent SQL injection
        $allowed_columns = ['id', 'title', 'show_date', 'start_time', 'end_time', 'status', 'created_at', 'festival_id', 'venue_id'];
        $order_by = in_array($args['order_by'], $allowed_columns, true) ? $args['order_by'] : 'show_date';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY s.{$order_by} {$order}";

        if ($args['limit'] > 0) {
            $sql .= " LIMIT %d OFFSET %d";
            $values[] = $args['limit'];
            $values[] = $args['offset'];
        }

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, ...$values);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Get scheduled shows from today onward.
     *
     * @param int|null $festival_id Optional festival filter.
     * @param int      $limit       Maximum results to return. Zero is unlimited.
     * @return array
     */
    public static function get_upcoming(?int $festival_id = null, int $limit = 0): array {
        return self::get_all([
            'festival_id' => $festival_id,
            'status' => 'scheduled',
            'date_from' => current_time('Y-m-d'),
            'limit' => max(0, $limit),
        ]);
    }

    /**
     * Get shows in an inclusive date range.
     *
     * Reversed ranges are rejected instead of silently issuing a misleading
     * query that can never match.
     *
     * @param string   $date_from   First date in Y-m-d format.
     * @param string   $date_to     Last date in Y-m-d format.
     * @param int|null $festival_id Optional festival filter.
     * @return array
     */
    public static function get_by_date_range(string $date_from, string $date_to, ?int $festival_id = null): array {
        if (!self::is_valid_date($date_from) || !self::is_valid_date($date_to) || $date_from > $date_to) {
            return [];
        }

        return self::get_all([
            'festival_id' => $festival_id,
            'date_from' => $date_from,
            'date_to' => $date_to,
        ]);
    }

    /**
     * Validate a calendar date without allowing PHP's rollover normalization.
     */
    private static function is_valid_date(string $date): bool {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    /**
     * Get a show by ID.
     *
     * @since 1.0.0
     *
     * @param int $id The show ID.
     * @return object|null The show object or null if not found.
     */
    public static function get_by_id(int $id): ?object {
        return Peanut_Festival_Database::get_row('shows', ['id' => $id]);
    }

    /**
     * Get a show by slug.
     *
     * @since 1.0.0
     *
     * @param string $slug The show slug.
     * @return object|null The show object or null if not found.
     */
    public static function get_by_slug(string $slug): ?object {
        return Peanut_Festival_Database::get_row('shows', ['slug' => $slug]);
    }

    /**
     * Create a new show.
     *
     * Automatically generates a URL-friendly slug from the title.
     * Invalidates the events cache.
     *
     * @since 1.0.0
     *
     * @param array $data Show data (title, festival_id, venue_id, show_date, times, etc.).
     * @return int|false The new show ID on success, false on failure.
     */
    public static function create(array $data): int|false {
        $data['slug'] = sanitize_title($data['title']);
        $data['created_at'] = current_time('mysql');

        $result = Peanut_Festival_Database::insert('shows', $data);

        if ($result) {
            Peanut_Festival_Cache::invalidate_group('events');
            Peanut_Festival_Cache::invalidate_group('shows');
            Peanut_Festival_Cache::invalidate_group('stats');
        }

        return $result;
    }

    /**
     * Update an existing show.
     *
     * Invalidates the events cache.
     *
     * @since 1.0.0
     *
     * @param int   $id   The show ID to update.
     * @param array $data Fields to update.
     * @return int|false Number of rows updated on success, false on failure.
     */
    public static function update(int $id, array $data): int|false {
        $data['updated_at'] = current_time('mysql');

        $result = Peanut_Festival_Database::update('shows', $data, ['id' => $id]);

        if ($result) {
            Peanut_Festival_Cache::invalidate_group('events');
            Peanut_Festival_Cache::invalidate_group('shows');
        }

        return $result;
    }

    /**
     * Delete a show and its performer assignments.
     *
     * Invalidates the events cache.
     *
     * @since 1.0.0
     *
     * @param int $id The show ID to delete.
     * @return int|false Number of rows deleted on success, false on failure.
     */
    public static function delete(int $id): int|false {
        // Also delete show-performer assignments
        Peanut_Festival_Database::delete('show_performers', ['show_id' => $id]);

        $result = Peanut_Festival_Database::delete('shows', ['id' => $id]);

        if ($result) {
            Peanut_Festival_Cache::invalidate_group('events');
            Peanut_Festival_Cache::invalidate_group('shows');
            Peanut_Festival_Cache::invalidate_group('stats');
        }

        return $result;
    }

    /**
     * Get all performers assigned to a show.
     *
     * Returns performers with assignment details (slot order, set length, confirmation).
     *
     * @since 1.0.0
     *
     * @param int $show_id The show ID.
     * @return array Array of performer objects with assignment details.
     */
    public static function get_performers(int $show_id): array {
        global $wpdb;
        $sp_table = Peanut_Festival_Database::get_table_name('show_performers');
        $p_table = Peanut_Festival_Database::get_table_name('performers');

        $sql = $wpdb->prepare(
            "SELECT p.*, sp.slot_order, sp.set_length_minutes, sp.performance_time, sp.confirmed, sp.notes as assignment_notes
             FROM $sp_table sp
             JOIN $p_table p ON sp.performer_id = p.id
             WHERE sp.show_id = %d
             ORDER BY sp.slot_order ASC",
            $show_id
        );

        return $wpdb->get_results($sql);
    }

    /**
     * Add a performer to a show.
     *
     * Creates a show-performer assignment with optional slot ordering and set length.
     *
     * @since 1.0.0
     *
     * @param int   $show_id      The show ID.
     * @param int   $performer_id The performer ID.
     * @param array $data         Optional additional data (slot_order, set_length_minutes, etc.).
     * @return int|false The assignment ID on success, false on failure.
     */
    public static function add_performer(int $show_id, int $performer_id, array $data = []): int|false {
        $insert_data = array_merge([
            'show_id' => $show_id,
            'performer_id' => $performer_id,
            'slot_order' => 0,
        ], $data);

        return Peanut_Festival_Database::insert('show_performers', $insert_data);
    }

    /**
     * Remove a performer from a show.
     *
     * @since 1.0.0
     *
     * @param int $show_id      The show ID.
     * @param int $performer_id The performer ID.
     * @return int|false Number of rows deleted on success, false on failure.
     */
    public static function remove_performer(int $show_id, int $performer_id): int|false {
        return Peanut_Festival_Database::delete('show_performers', [
            'show_id' => $show_id,
            'performer_id' => $performer_id,
        ]);
    }

    /**
     * Count shows matching criteria.
     *
     * @since 1.0.0
     *
     * @param array $where Optional conditions for counting.
     * @return int The number of matching shows.
     */
    public static function count(array $where = []): int {
        return Peanut_Festival_Database::count('shows', $where);
    }

    /**
     * Mark a show as completed.
     *
     * Fires the show_completed hook for Booker integration.
     *
     * @since 1.1.0
     *
     * @param int $id The show ID.
     * @return int|false Rows updated or false.
     */
    public static function complete(int $id): int|false {
        $result = self::update($id, [
            'status' => 'completed',
            'completed_at' => current_time('mysql'),
        ]);

        if ($result !== false) {
            // Get performer IDs for this show
            $performers = self::get_performers($id);
            $performer_ids = array_map(function($p) {
                return (int) $p->id;
            }, $performers);

            // Fire hook for Booker integration
            Peanut_Festival_Booker_Integration::fire_show_completed($id, $performer_ids);
        }

        return $result;
    }

    /**
     * Schedule performers for a show.
     *
     * Updates the show with performers and fires scheduling hook for calendar sync.
     *
     * @since 1.1.0
     *
     * @param int   $show_id      Show ID.
     * @param array $performer_ids Array of performer IDs to add.
     * @param array $options      Optional settings per performer (keyed by performer_id).
     * @return bool Success.
     */
    public static function schedule_performers(int $show_id, array $performer_ids, array $options = []): bool {
        $show = self::get_by_id($show_id);
        if (!$show) {
            return false;
        }

        // Clear existing performers
        Peanut_Festival_Database::delete('show_performers', ['show_id' => $show_id]);

        // Add each performer
        $slot = 1;
        foreach ($performer_ids as $performer_id) {
            $performer_options = $options[$performer_id] ?? [];
            self::add_performer($show_id, $performer_id, array_merge([
                'slot_order' => $slot++,
            ], $performer_options));
        }

        // Fire hook for Booker calendar sync
        if (!empty($performer_ids)) {
            Peanut_Festival_Booker_Integration::fire_show_scheduled(
                $show_id,
                $performer_ids,
                $show->show_date,
                $show->start_time ?? '00:00:00',
                $show->end_time ?? '23:59:59'
            );
        }

        return true;
    }

    /**
     * Check if performer has a conflict for a given date/time.
     *
     * Checks both Festival shows and Booker availability (if integration enabled).
     *
     * @since 1.1.0
     *
     * @param int    $performer_id Performer ID.
     * @param string $date Show date (Y-m-d).
     * @param string $start_time Start time.
     * @param string $end_time End time.
     * @param int    $exclude_show_id Optional show ID to exclude from conflict check.
     * @return bool True if conflict exists.
     */
    public static function has_schedule_conflict(
        int $performer_id,
        string $date,
        string $start_time,
        string $end_time,
        int $exclude_show_id = 0
    ): bool {
        global $wpdb;
        $shows_table = Peanut_Festival_Database::get_table_name('shows');
        $sp_table = Peanut_Festival_Database::get_table_name('show_performers');

        // Check Festival shows
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM $sp_table sp
             JOIN $shows_table s ON sp.show_id = s.id
             WHERE sp.performer_id = %d
             AND s.show_date = %s
             AND s.status NOT IN ('cancelled', 'completed')
             AND (
                 (s.start_time <= %s AND s.end_time > %s)
                 OR (s.start_time < %s AND s.end_time >= %s)
                 OR (s.start_time >= %s AND s.end_time <= %s)
             )",
            $performer_id,
            $date,
            $start_time, $start_time,
            $end_time, $end_time,
            $start_time, $end_time
        );

        if ($exclude_show_id) {
            $sql .= $wpdb->prepare(" AND s.id != %d", $exclude_show_id);
        }

        $festival_conflict = (int) $wpdb->get_var($sql) > 0;

        if ($festival_conflict) {
            return true;
        }

        // Check Booker availability if integration is enabled
        $booker = Peanut_Festival_Booker_Integration::get_instance();
        if ($booker->is_enabled() && $booker->is_booker_active()) {
            $link = $booker->get_link_by_festival_id($performer_id);
            if ($link && $link->booker_performer_id) {
                $blocked = $booker->get_booker_availability(
                    $link->booker_performer_id,
                    $date,
                    $date
                );

                foreach ($blocked as $block) {
                    // Simple overlap check for blocked dates
                    if ($block['date'] === $date) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
