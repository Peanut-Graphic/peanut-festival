<?php
/**
 * Competitions Management Class
 *
 * Handles tournament brackets, elimination competitions, and head-to-head voting.
 * Supports single elimination, double elimination, and round robin formats.
 *
 * @package    Peanut_Festival
 * @subpackage Includes
 * @since      1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load competition traits.
require_once __DIR__ . '/competitions/trait-rest-endpoints.php';
require_once __DIR__ . '/competitions/trait-bracket-visualization.php';

/**
 * Class Peanut_Festival_Competitions
 *
 * Manages competition brackets and tournament-style performer matchups.
 *
 * @since 1.1.0
 */
class Peanut_Festival_Competitions {

    use Peanut_Festival_Competitions_REST_Endpoints;
    use Peanut_Festival_Competitions_Bracket_Visualization;

    /**
     * Singleton instance.
     *
     * @since 1.1.0
     * @var Peanut_Festival_Competitions|null
     */
    private static ?Peanut_Festival_Competitions $instance = null;

    /**
     * Competition types.
     */
    public const TYPE_SINGLE_ELIMINATION = 'single_elimination';
    public const TYPE_DOUBLE_ELIMINATION = 'double_elimination';
    public const TYPE_ROUND_ROBIN = 'round_robin';

    /**
     * Voting methods.
     */
    public const VOTING_HEAD_TO_HEAD = 'head_to_head';
    public const VOTING_BORDA = 'borda';
    public const VOTING_JUDGES = 'judges';
    public const VOTING_COMBINED = 'combined';

    /**
     * Competition statuses.
     */
    public const STATUS_SETUP = 'setup';
    public const STATUS_REGISTRATION = 'registration';
    public const STATUS_SEEDING = 'seeding';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Match statuses.
     */
    public const MATCH_PENDING = 'pending';
    public const MATCH_SCHEDULED = 'scheduled';
    public const MATCH_VOTING = 'voting';
    public const MATCH_COMPLETED = 'completed';
    public const MATCH_BYE = 'bye';

    /**
     * Get singleton instance.
     *
     * @since 1.1.0
     * @return Peanut_Festival_Competitions The singleton instance.
     */
    public static function get_instance(): Peanut_Festival_Competitions {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton pattern.
     *
     * @since 1.1.0
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     *
     * @since 1.1.0
     */
    private function init_hooks(): void {
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Cron for auto-advancing matches
        add_action('peanut_festival_check_match_deadlines', [$this, 'check_match_deadlines']);
        if (!wp_next_scheduled('peanut_festival_check_match_deadlines')) {
            wp_schedule_event(time(), 'five_minutes', 'peanut_festival_check_match_deadlines');
        }
    }

    // =========================================================================
    // Competition CRUD
    // =========================================================================

    /**
     * Get all competitions with optional filtering.
     *
     * @since 1.1.0
     *
     * @param array $args {
     *     Optional arguments.
     *
     *     @type int    $festival_id Filter by festival.
     *     @type string $status Filter by status.
     *     @type string $type Filter by competition type.
     *     @type string $order_by Column to sort by.
     *     @type string $order Sort direction.
     *     @type int    $limit Maximum results.
     *     @type int    $offset Pagination offset.
     * }
     * @return array Array of competition objects.
     */
    public static function get_all(array $args = []): array {
        $defaults = [
            'festival_id' => null,
            'status' => '',
            'type' => '',
            'order_by' => 'created_at',
            'order' => 'DESC',
            'limit' => 0,
            'offset' => 0,
        ];
        $args = wp_parse_args($args, $defaults);

        global $wpdb;
        $table = Peanut_Festival_Database::get_table_name('competitions');

        $sql = "SELECT * FROM $table WHERE 1=1";
        $values = [];

        if ($args['festival_id']) {
            $sql .= " AND festival_id = %d";
            $values[] = $args['festival_id'];
        }

        if ($args['status']) {
            $sql .= " AND status = %s";
            $values[] = $args['status'];
        }

        if ($args['type']) {
            $sql .= " AND competition_type = %s";
            $values[] = $args['type'];
        }

        $allowed_columns = ['id', 'name', 'status', 'competition_type', 'created_at', 'scheduled_start'];
        $order_by = in_array($args['order_by'], $allowed_columns, true) ? $args['order_by'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$order_by} {$order}";

        if ($args['limit'] > 0) {
            $sql .= " LIMIT %d OFFSET %d";
            $values[] = $args['limit'];
            $values[] = $args['offset'];
        }

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, ...$values);
        }

        $results = $wpdb->get_results($sql);

        // Decode config JSON
        foreach ($results as &$competition) {
            if (!empty($competition->config)) {
                $competition->config = json_decode($competition->config, true);
            }
        }

        return $results;
    }

    /**
     * Get competition by ID.
     *
     * @since 1.1.0
     *
     * @param int $id Competition ID.
     * @return object|null Competition object or null.
     */
    public static function get_by_id(int $id): ?object {
        $competition = Peanut_Festival_Database::get_row('competitions', ['id' => $id]);

        if ($competition && !empty($competition->config)) {
            $competition->config = json_decode($competition->config, true);
        }

        return $competition;
    }

    /**
     * Create a new competition.
     *
     * @since 1.1.0
     *
     * @param array $data Competition data.
     * @return int|false Competition ID or false on failure.
     */
    public static function create(array $data): int|false {
        $defaults = [
            'competition_type' => self::TYPE_SINGLE_ELIMINATION,
            'voting_method' => self::VOTING_HEAD_TO_HEAD,
            'status' => self::STATUS_SETUP,
            'rounds_count' => 0,
            'current_round' => 0,
            'config' => [],
        ];
        $data = wp_parse_args($data, $defaults);

        // Encode config as JSON
        if (is_array($data['config'])) {
            $data['config'] = wp_json_encode($data['config']);
        }

        $data['created_by'] = get_current_user_id();
        $data['created_at'] = current_time('mysql');

        return Peanut_Festival_Database::insert('competitions', $data);
    }

    /**
     * Update a competition.
     *
     * @since 1.1.0
     *
     * @param int   $id Competition ID.
     * @param array $data Fields to update.
     * @return int|false Rows updated or false.
     */
    public static function update(int $id, array $data): int|false {
        if (isset($data['config']) && is_array($data['config'])) {
            $data['config'] = wp_json_encode($data['config']);
        }

        $data['updated_at'] = current_time('mysql');

        return Peanut_Festival_Database::update('competitions', $data, ['id' => $id]);
    }

    /**
     * Delete a competition and its matches.
     *
     * @since 1.1.0
     *
     * @param int $id Competition ID.
     * @return int|false Rows deleted or false.
     */
    public static function delete(int $id): int|false {
        // Delete all matches first
        Peanut_Festival_Database::delete('competition_matches', ['competition_id' => $id]);

        return Peanut_Festival_Database::delete('competitions', ['id' => $id]);
    }

    // =========================================================================
    // Match CRUD
    // =========================================================================

    /**
     * Get matches for a competition.
     *
     * @since 1.1.0
     *
     * @param int   $competition_id Competition ID.
     * @param array $args Optional filters.
     * @return array Array of match objects.
     */
    public static function get_matches(int $competition_id, array $args = []): array {
        $defaults = [
            'round_number' => null,
            'status' => '',
            'order_by' => 'round_number',
            'order' => 'ASC',
        ];
        $args = wp_parse_args($args, $defaults);

        global $wpdb;
        $table = Peanut_Festival_Database::get_table_name('competition_matches');
        $performers_table = Peanut_Festival_Database::get_table_name('performers');

        $sql = "SELECT m.*,
                       p1.name as performer_1_name,
                       p2.name as performer_2_name,
                       pw.name as winner_name
                FROM $table m
                LEFT JOIN $performers_table p1 ON m.performer_1_id = p1.id
                LEFT JOIN $performers_table p2 ON m.performer_2_id = p2.id
                LEFT JOIN $performers_table pw ON m.winner_id = pw.id
                WHERE m.competition_id = %d";
        $values = [$competition_id];

        if ($args['round_number'] !== null) {
            $sql .= " AND m.round_number = %d";
            $values[] = $args['round_number'];
        }

        if ($args['status']) {
            $sql .= " AND m.status = %s";
            $values[] = $args['status'];
        }

        $order_by = in_array($args['order_by'], ['round_number', 'match_number', 'scheduled_time'], true)
            ? $args['order_by']
            : 'round_number';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY m.{$order_by} {$order}, m.match_number ASC";

        $sql = $wpdb->prepare($sql, ...$values);

        return $wpdb->get_results($sql);
    }

    /**
     * Get a single match by ID.
     *
     * @since 1.1.0
     *
     * @param int $match_id Match ID.
     * @return object|null Match object or null.
     */
    public static function get_match(int $match_id): ?object {
        return Peanut_Festival_Database::get_row('competition_matches', ['id' => $match_id]);
    }

    /**
     * Create a match.
     *
     * @since 1.1.0
     *
     * @param array $data Match data.
     * @return int|false Match ID or false.
     */
    public static function create_match(array $data): int|false {
        $data['created_at'] = current_time('mysql');
        return Peanut_Festival_Database::insert('competition_matches', $data);
    }

    /**
     * Update a match.
     *
     * @since 1.1.0
     *
     * @param int   $match_id Match ID.
     * @param array $data Fields to update.
     * @return int|false Rows updated or false.
     */
    public static function update_match(int $match_id, array $data): int|false {
        $data['updated_at'] = current_time('mysql');
        return Peanut_Festival_Database::update('competition_matches', $data, ['id' => $match_id]);
    }

    // =========================================================================
    // Bracket Generation
    // =========================================================================

    /**
     * Generate bracket for a competition.
     *
     * @since 1.1.0
     *
     * @param int   $competition_id Competition ID.
     * @param array $performer_ids Array of performer IDs (in seed order).
     * @return bool Success.
     */
    public static function generate_bracket(int $competition_id, array $performer_ids): bool {
        $competition = self::get_by_id($competition_id);
        if (!$competition) {
            return false;
        }

        // Clear existing matches
        Peanut_Festival_Database::delete('competition_matches', ['competition_id' => $competition_id]);

        $count = count($performer_ids);
        if ($count < 2) {
            return false;
        }

        switch ($competition->competition_type) {
            case self::TYPE_SINGLE_ELIMINATION:
                return self::generate_single_elimination($competition_id, $performer_ids);

            case self::TYPE_DOUBLE_ELIMINATION:
                return self::generate_double_elimination($competition_id, $performer_ids);

            case self::TYPE_ROUND_ROBIN:
                return self::generate_round_robin($competition_id, $performer_ids);

            default:
                return false;
        }
    }

    /**
     * Generate single elimination bracket.
     *
     * @since 1.1.0
     *
     * @param int   $competition_id Competition ID.
     * @param array $performer_ids Performer IDs in seed order.
     * @return bool Success.
     */
    private static function generate_single_elimination(int $competition_id, array $performer_ids): bool {
        $count = count($performer_ids);

        // Calculate number of rounds needed
        $rounds = (int) ceil(log($count, 2));

        // Calculate bracket size (next power of 2)
        $bracket_size = pow(2, $rounds);

        // Calculate number of byes needed
        $byes = $bracket_size - $count;

        // Seed the bracket (standard tournament seeding)
        $seeded = self::seed_bracket($performer_ids, $bracket_size);

        // Generate first round matches
        $match_number = 1;
        $first_round_matches = [];

        for ($i = 0; $i < $bracket_size; $i += 2) {
            $p1_id = $seeded[$i];
            $p2_id = $seeded[$i + 1];

            $match_data = [
                'competition_id' => $competition_id,
                'round_number' => 1,
                'match_number' => $match_number,
                'bracket_position' => "R1M{$match_number}",
                'performer_1_id' => $p1_id,
                'performer_2_id' => $p2_id,
                'performer_1_seed' => $p1_id ? array_search($p1_id, $performer_ids) + 1 : null,
                'performer_2_seed' => $p2_id ? array_search($p2_id, $performer_ids) + 1 : null,
                'status' => self::MATCH_PENDING,
            ];

            // Handle byes
            if (!$p1_id && $p2_id) {
                $match_data['winner_id'] = $p2_id;
                $match_data['status'] = self::MATCH_BYE;
            } elseif ($p1_id && !$p2_id) {
                $match_data['winner_id'] = $p1_id;
                $match_data['status'] = self::MATCH_BYE;
            }

            $match_id = self::create_match($match_data);
            $first_round_matches[] = $match_id;
            $match_number++;
        }

        // Generate subsequent round placeholders
        $matches_in_round = count($first_round_matches) / 2;
        for ($round = 2; $round <= $rounds; $round++) {
            for ($m = 1; $m <= $matches_in_round; $m++) {
                self::create_match([
                    'competition_id' => $competition_id,
                    'round_number' => $round,
                    'match_number' => $m,
                    'bracket_position' => "R{$round}M{$m}",
                    'status' => self::MATCH_PENDING,
                ]);
            }
            $matches_in_round = (int) ($matches_in_round / 2);
        }

        // Update competition
        self::update($competition_id, [
            'rounds_count' => $rounds,
            'current_round' => 1,
            'status' => self::STATUS_ACTIVE,
        ]);

        // Advance any bye winners
        self::advance_bye_winners($competition_id);

        return true;
    }

    /**
     * Generate double elimination bracket.
     *
     * Double elimination consists of:
     * - Winners bracket (upper): Standard single elimination
     * - Losers bracket (lower): Second chance for eliminated performers
     * - Grand finals: Winners bracket winner vs Losers bracket winner
     * - Grand finals reset: If losers bracket winner wins, a deciding match is played
     *
     * Bracket positions use format:
     * - W_R{round}M{match} for winners bracket
     * - L_R{round}M{match} for losers bracket
     * - GF for grand finals, GFR for grand finals reset
     *
     * @since 1.1.0
     * @since 1.3.0 Full implementation with losers bracket.
     *
     * @param int   $competition_id Competition ID.
     * @param array $performer_ids Performer IDs in seed order.
     * @return bool Success.
     */
    private static function generate_double_elimination(int $competition_id, array $performer_ids): bool {
        $count = count($performer_ids);

        // Calculate winners bracket rounds
        $winners_rounds = (int) ceil(log($count, 2));
        $bracket_size = pow(2, $winners_rounds);
        $byes = $bracket_size - $count;

        // Losers bracket has (2 * winners_rounds - 1) rounds
        // Each winners round feeds into losers, plus losers bracket progression
        $losers_rounds = 2 * $winners_rounds - 1;

        // Total rounds: winners rounds + losers rounds + grand finals (+ potential reset)
        $total_rounds = $winners_rounds + $losers_rounds + 2;

        // Seed the winners bracket
        $seeded = self::seed_bracket($performer_ids, $bracket_size);

        // =========================================================================
        // Generate Winners Bracket (Upper Bracket)
        // =========================================================================
        $match_number = 1;
        $winners_matches = [];

        // First round of winners bracket
        for ($i = 0; $i < $bracket_size; $i += 2) {
            $p1_id = $seeded[$i];
            $p2_id = $seeded[$i + 1];

            $match_data = [
                'competition_id' => $competition_id,
                'round_number' => 1,
                'match_number' => $match_number,
                'bracket_position' => "W_R1M{$match_number}",
                'bracket_type' => 'winners',
                'performer_1_id' => $p1_id,
                'performer_2_id' => $p2_id,
                'performer_1_seed' => $p1_id ? array_search($p1_id, $performer_ids) + 1 : null,
                'performer_2_seed' => $p2_id ? array_search($p2_id, $performer_ids) + 1 : null,
                'status' => self::MATCH_PENDING,
            ];

            // Handle byes
            if (!$p1_id && $p2_id) {
                $match_data['winner_id'] = $p2_id;
                $match_data['loser_id'] = null;
                $match_data['status'] = self::MATCH_BYE;
            } elseif ($p1_id && !$p2_id) {
                $match_data['winner_id'] = $p1_id;
                $match_data['loser_id'] = null;
                $match_data['status'] = self::MATCH_BYE;
            }

            $match_id = self::create_match($match_data);
            $winners_matches[1][$match_number] = $match_id;
            $match_number++;
        }

        // Subsequent winners bracket rounds
        $matches_in_round = count($winners_matches[1]) / 2;
        for ($round = 2; $round <= $winners_rounds; $round++) {
            for ($m = 1; $m <= $matches_in_round; $m++) {
                $match_id = self::create_match([
                    'competition_id' => $competition_id,
                    'round_number' => $round,
                    'match_number' => $m,
                    'bracket_position' => "W_R{$round}M{$m}",
                    'bracket_type' => 'winners',
                    'status' => self::MATCH_PENDING,
                ]);
                $winners_matches[$round][$m] = $match_id;
            }
            $matches_in_round = (int) ($matches_in_round / 2);
        }

        // =========================================================================
        // Generate Losers Bracket (Lower Bracket)
        // =========================================================================
        $losers_matches = [];
        $losers_round = 1;
        $winners_round_feeding = 1;

        // Losers bracket round structure:
        // - Odd losers rounds: Receive losers from winners bracket
        // - Even losers rounds: Internal progression (no new entrants)
        // The number of matches varies based on which winners round feeds in

        $matches_from_w1 = $bracket_size / 2; // Matches in winners round 1

        // First losers round: receives losers from winners round 1
        // These play against each other
        $l_matches = $matches_from_w1 / 2;
        for ($m = 1; $m <= $l_matches; $m++) {
            $match_id = self::create_match([
                'competition_id' => $competition_id,
                'round_number' => $winners_rounds + $losers_round,
                'match_number' => $m,
                'bracket_position' => "L_R{$losers_round}M{$m}",
                'bracket_type' => 'losers',
                'receives_losers_from_round' => 1,
                'status' => self::MATCH_PENDING,
            ]);
            $losers_matches[$losers_round][$m] = $match_id;
        }
        $losers_round++;

        // Continue building losers bracket
        // Pattern: receive from winners, then internal round, repeat
        for ($w_round = 2; $w_round <= $winners_rounds; $w_round++) {
            // Losers round that receives from winners bracket
            $prev_losers_count = count($losers_matches[$losers_round - 1] ?? []);
            $incoming_losers = $bracket_size / pow(2, $w_round); // Losers from this winners round
            $l_matches = max($prev_losers_count, $incoming_losers);

            for ($m = 1; $m <= $l_matches; $m++) {
                $match_id = self::create_match([
                    'competition_id' => $competition_id,
                    'round_number' => $winners_rounds + $losers_round,
                    'match_number' => $m,
                    'bracket_position' => "L_R{$losers_round}M{$m}",
                    'bracket_type' => 'losers',
                    'receives_losers_from_round' => $w_round,
                    'status' => self::MATCH_PENDING,
                ]);
                $losers_matches[$losers_round][$m] = $match_id;
            }
            $losers_round++;

            // Internal progression round (no new entrants from winners)
            if ($l_matches > 1) {
                $l_matches = (int) ceil($l_matches / 2);
                for ($m = 1; $m <= $l_matches; $m++) {
                    $match_id = self::create_match([
                        'competition_id' => $competition_id,
                        'round_number' => $winners_rounds + $losers_round,
                        'match_number' => $m,
                        'bracket_position' => "L_R{$losers_round}M{$m}",
                        'bracket_type' => 'losers',
                        'status' => self::MATCH_PENDING,
                    ]);
                    $losers_matches[$losers_round][$m] = $match_id;
                }
                $losers_round++;
            }
        }

        // =========================================================================
        // Generate Grand Finals
        // =========================================================================
        $gf_round = $winners_rounds + $losers_round;

        // Grand Finals: Winners bracket champion vs Losers bracket champion
        self::create_match([
            'competition_id' => $competition_id,
            'round_number' => $gf_round,
            'match_number' => 1,
            'bracket_position' => 'GF',
            'bracket_type' => 'grand_finals',
            'status' => self::MATCH_PENDING,
        ]);

        // Grand Finals Reset: Only played if losers bracket winner wins GF
        self::create_match([
            'competition_id' => $competition_id,
            'round_number' => $gf_round + 1,
            'match_number' => 1,
            'bracket_position' => 'GFR',
            'bracket_type' => 'grand_finals_reset',
            'status' => self::MATCH_PENDING,
        ]);

        // Store bracket metadata in config
        $config = [
            'winners_rounds' => $winners_rounds,
            'losers_rounds' => $losers_round - 1,
            'bracket_size' => $bracket_size,
            'has_grand_finals_reset' => true,
        ];

        self::update($competition_id, [
            'rounds_count' => $gf_round + 1,
            'current_round' => 1,
            'status' => self::STATUS_ACTIVE,
            'config' => $config,
        ]);

        // Advance bye winners in winners bracket
        self::advance_double_elim_bye_winners($competition_id);

        return true;
    }

    /**
     * Advance bye winners in double elimination.
     *
     * @since 1.3.0
     *
     * @param int $competition_id Competition ID.
     */
    private static function advance_double_elim_bye_winners(int $competition_id): void {
        $bye_matches = self::get_matches($competition_id, ['status' => self::MATCH_BYE]);

        foreach ($bye_matches as $match) {
            if ($match->winner_id && $match->bracket_type === 'winners') {
                self::advance_double_elim_winner($match, $match->winner_id, null);
            }
        }
    }

    /**
     * Advance winner in double elimination bracket.
     *
     * @since 1.3.0
     *
     * @param object   $match Current match.
     * @param int      $winner_id Winner performer ID.
     * @param int|null $loser_id Loser performer ID (null for byes).
     */
    private static function advance_double_elim_winner(object $match, int $winner_id, ?int $loser_id): void {
        $competition = self::get_by_id($match->competition_id);
        if (!$competition) {
            return;
        }

        $config = $competition->config ?? [];
        $winners_rounds = $config['winners_rounds'] ?? 0;

        // Handle based on bracket type
        switch ($match->bracket_type) {
            case 'winners':
                // Advance winner to next winners bracket round
                self::advance_to_winners_round($match, $winner_id, $winners_rounds);

                // Send loser to losers bracket (if not a bye)
                if ($loser_id) {
                    self::send_to_losers_bracket($match, $loser_id, $winners_rounds);
                }
                break;

            case 'losers':
                // Advance winner to next losers bracket round or grand finals
                self::advance_in_losers_bracket($match, $winner_id, $winners_rounds);
                break;

            case 'grand_finals':
                // Check if reset is needed
                self::handle_grand_finals_result($match, $winner_id, $loser_id);
                break;

            case 'grand_finals_reset':
                // This is the final match - declare champion
                self::declare_champion($match->competition_id, $winner_id);
                break;
        }
    }

    /**
     * Advance winner to next winners bracket round.
     *
     * @since 1.3.0
     */
    private static function advance_to_winners_round(object $match, int $winner_id, int $winners_rounds): void {
        $next_round = $match->round_number + 1;

        if ($next_round > $winners_rounds) {
            // Winner advances to grand finals
            $gf_match = self::get_match_by_position($match->competition_id, 'GF');
            if ($gf_match) {
                self::update_match($gf_match->id, ['performer_1_id' => $winner_id]);
            }
            return;
        }

        $next_match_number = (int) ceil($match->match_number / 2);
        $is_top = ($match->match_number % 2) === 1;

        $next_match = self::get_match_by_position(
            $match->competition_id,
            "W_R{$next_round}M{$next_match_number}"
        );

        if ($next_match) {
            $update_data = $is_top
                ? ['performer_1_id' => $winner_id]
                : ['performer_2_id' => $winner_id];
            self::update_match($next_match->id, $update_data);
        }
    }

    /**
     * Send loser to losers bracket.
     *
     * @since 1.3.0
     */
    private static function send_to_losers_bracket(object $match, int $loser_id, int $winners_rounds): void {
        // Determine which losers bracket round receives this loser
        // Winners R1 losers go to L_R1
        // Winners R2 losers go to L_R2 (paired with L_R1 winners)
        // And so on...

        $w_round = $match->round_number;

        // Find the losers bracket match that receives from this winners round
        global $wpdb;
        $table = Peanut_Festival_Database::get_table_name('competition_matches');

        $losers_match = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table
             WHERE competition_id = %d
             AND bracket_type = 'losers'
             AND receives_losers_from_round = %d
             AND (performer_1_id IS NULL OR performer_2_id IS NULL)
             ORDER BY match_number ASC
             LIMIT 1",
            $match->competition_id,
            $w_round
        ));

        if ($losers_match) {
            $update_data = $losers_match->performer_1_id === null
                ? ['performer_1_id' => $loser_id]
                : ['performer_2_id' => $loser_id];
            self::update_match($losers_match->id, $update_data);
        }
    }

    /**
     * Advance winner in losers bracket.
     *
     * @since 1.3.0
     */
    private static function advance_in_losers_bracket(object $match, int $winner_id, int $winners_rounds): void {
        // Parse current losers round from bracket_position (L_R{n}M{m})
        preg_match('/L_R(\d+)M(\d+)/', $match->bracket_position, $matches);
        $current_l_round = (int) ($matches[1] ?? 0);

        // Find next losers bracket match
        $next_l_round = $current_l_round + 1;
        $next_match_number = (int) ceil($match->match_number / 2);

        $next_match = self::get_match_by_position(
            $match->competition_id,
            "L_R{$next_l_round}M{$next_match_number}"
        );

        if ($next_match) {
            $is_top = ($match->match_number % 2) === 1;
            $update_data = $is_top
                ? ['performer_1_id' => $winner_id]
                : ['performer_2_id' => $winner_id];
            self::update_match($next_match->id, $update_data);
        } else {
            // No more losers rounds - winner goes to grand finals
            $gf_match = self::get_match_by_position($match->competition_id, 'GF');
            if ($gf_match) {
                self::update_match($gf_match->id, ['performer_2_id' => $winner_id]);
            }
        }
    }

    /**
     * Handle grand finals result.
     *
     * @since 1.3.0
     */
    private static function handle_grand_finals_result(object $match, int $winner_id, ?int $loser_id): void {
        // performer_1 is from winners bracket (hasn't lost yet)
        // performer_2 is from losers bracket (has one loss)

        if ($winner_id === $match->performer_1_id) {
            // Winners bracket champion wins - they are the champion
            self::declare_champion($match->competition_id, $winner_id);

            // Mark reset match as not needed
            $reset_match = self::get_match_by_position($match->competition_id, 'GFR');
            if ($reset_match) {
                self::update_match($reset_match->id, ['status' => self::MATCH_BYE]);
            }
        } else {
            // Losers bracket champion wins - need reset match
            // Now both have one loss each
            $reset_match = self::get_match_by_position($match->competition_id, 'GFR');
            if ($reset_match) {
                self::update_match($reset_match->id, [
                    'performer_1_id' => $match->performer_1_id, // Original winners bracket champ
                    'performer_2_id' => $winner_id, // Losers bracket champ who won GF
                ]);
            }
        }
    }

    /**
     * Declare competition champion.
     *
     * @since 1.3.0
     */
    private static function declare_champion(int $competition_id, int $winner_id): void {
        self::update($competition_id, [
            'winner_performer_id' => $winner_id,
            'status' => self::STATUS_COMPLETED,
            'completed_at' => current_time('mysql'),
        ]);

        /** This action is documented in class-competitions.php */
        do_action('peanut_festival_competition_completed', $competition_id, $winner_id);
    }

    /**
     * Get match by bracket position.
     *
     * @since 1.3.0
     *
     * @param int    $competition_id Competition ID.
     * @param string $position Bracket position (e.g., "W_R2M1", "L_R1M3", "GF").
     * @return object|null Match object or null.
     */
    private static function get_match_by_position(int $competition_id, string $position): ?object {
        global $wpdb;
        $table = Peanut_Festival_Database::get_table_name('competition_matches');

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE competition_id = %d AND bracket_position = %s",
            $competition_id,
            $position
        ));
    }

    /**
     * Generate round robin schedule.
     *
     * @since 1.1.0
     *
     * @param int   $competition_id Competition ID.
     * @param array $performer_ids Performer IDs.
     * @return bool Success.
     */
    private static function generate_round_robin(int $competition_id, array $performer_ids): bool {
        $count = count($performer_ids);

        // Add dummy player if odd number
        if ($count % 2 !== 0) {
            $performer_ids[] = null; // Bye
            $count++;
        }

        $rounds = $count - 1;
        $matches_per_round = $count / 2;

        // Generate round robin schedule using circle method
        $players = $performer_ids;
        $fixed = array_shift($players); // Fix first player

        $match_number = 1;
        for ($round = 1; $round <= $rounds; $round++) {
            // First match: fixed player vs player at position 0
            $pairs = [];
            $pairs[] = [$fixed, $players[0]];

            // Remaining matches
            for ($i = 1; $i < $matches_per_round; $i++) {
                $pairs[] = [$players[$i], $players[$count - 1 - $i]];
            }

            // Create matches
            foreach ($pairs as $pair) {
                if ($pair[0] !== null && $pair[1] !== null) {
                    self::create_match([
                        'competition_id' => $competition_id,
                        'round_number' => $round,
                        'match_number' => $match_number,
                        'bracket_position' => "RR-R{$round}M{$match_number}",
                        'performer_1_id' => $pair[0],
                        'performer_2_id' => $pair[1],
                        'status' => self::MATCH_PENDING,
                    ]);
                    $match_number++;
                }
            }

            // Rotate players (not the fixed one)
            $last = array_pop($players);
            array_unshift($players, $last);
        }

        self::update($competition_id, [
            'rounds_count' => $rounds,
            'current_round' => 1,
            'status' => self::STATUS_ACTIVE,
        ]);

        return true;
    }

    /**
     * Seed bracket in standard tournament format.
     *
     * @since 1.1.0
     *
     * @param array $performers Performer IDs in seed order.
     * @param int   $size Bracket size.
     * @return array Seeded positions.
     */
    private static function seed_bracket(array $performers, int $size): array {
        $seeded = array_fill(0, $size, null);

        // Standard tournament seeding positions
        $positions = self::get_seed_positions($size);

        foreach ($performers as $seed => $performer_id) {
            if (isset($positions[$seed])) {
                $seeded[$positions[$seed]] = $performer_id;
            }
        }

        return $seeded;
    }

    /**
     * Get standard tournament seed positions.
     *
     * @since 1.1.0
     *
     * @param int $size Bracket size.
     * @return array Position map.
     */
    private static function get_seed_positions(int $size): array {
        // Standard seeding: 1 vs last, 2 vs second-last, etc.
        $positions = [];
        $matches = $size / 2;

        for ($i = 0; $i < $matches; $i++) {
            $positions[$i] = $i * 2;                    // Top seeds
            $positions[$size - 1 - $i] = $i * 2 + 1;   // Bottom seeds
        }

        return $positions;
    }

    /**
     * Advance winners from bye matches.
     *
     * @since 1.1.0
     *
     * @param int $competition_id Competition ID.
     */
    private static function advance_bye_winners(int $competition_id): void {
        $bye_matches = self::get_matches($competition_id, ['status' => self::MATCH_BYE]);

        foreach ($bye_matches as $match) {
            if ($match->winner_id) {
                self::advance_winner($match->id, $match->winner_id);
            }
        }
    }

    // =========================================================================
    // Match Operations
    // =========================================================================

    /**
     * Start voting for a match.
     *
     * @since 1.1.0
     *
     * @param int      $match_id Match ID.
     * @param int|null $duration_minutes Voting duration in minutes.
     * @return bool Success.
     */
    public static function start_match_voting(int $match_id, ?int $duration_minutes = 10): bool {
        $match = self::get_match($match_id);
        if (!$match || !$match->performer_1_id || !$match->performer_2_id) {
            return false;
        }

        $now = current_time('mysql');
        $closes = date('Y-m-d H:i:s', strtotime("+{$duration_minutes} minutes"));

        self::update_match($match_id, [
            'status' => self::MATCH_VOTING,
            'voting_opens_at' => $now,
            'voting_closes_at' => $closes,
            'started_at' => $now,
        ]);

        /**
         * Fires when match voting starts.
         *
         * @since 1.1.0
         *
         * @param int    $match_id Match ID.
         * @param object $match Match object.
         */
        do_action('peanut_festival_match_voting_started', $match_id, $match);

        return true;
    }

    /**
     * Submit vote for a match.
     *
     * One vote per voter per match is enforced atomically by the match_votes
     * ledger's UNIQUE keys; the vote count is incremented in SQL so concurrent
     * votes are never lost.
     *
     * @since 1.1.0
     * @since 1.4.1 Voter identity is server-derived; ledger + atomic increment.
     *
     * @param int    $match_id     Match ID.
     * @param int    $performer_id Performer being voted for.
     * @param string $voter_hash   Server-derived voter identifier (see Peanut_Festival_Voter_Identity).
     * @param string $client_hash  Optional keyed hash of client IP + User-Agent.
     * @return bool|WP_Error Success or error.
     */
    public static function submit_match_vote(int $match_id, int $performer_id, string $voter_hash, string $client_hash = ''): bool|WP_Error {
        global $wpdb;

        if ($voter_hash === '') {
            return new WP_Error('invalid_voter', 'Unable to identify voter');
        }

        $match = self::get_match($match_id);

        if (!$match) {
            return new WP_Error('invalid_match', 'Match not found');
        }

        if ($match->status !== self::MATCH_VOTING) {
            return new WP_Error('voting_closed', 'Voting is not open for this match');
        }

        // Check if voting period has ended
        if ($match->voting_closes_at && strtotime($match->voting_closes_at) < time()) {
            return new WP_Error('voting_closed', 'Voting period has ended');
        }

        // Validate performer is in this match
        if ($performer_id !== (int) $match->performer_1_id && $performer_id !== (int) $match->performer_2_id) {
            return new WP_Error('invalid_performer', 'Performer not in this match');
        }

        $ledger = Peanut_Festival_Database::get_table_name('match_votes');
        $matches = Peanut_Festival_Database::get_table_name('competition_matches');

        // Friendly early answer; the UNIQUE keys below are the real guarantee.
        $already = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $ledger WHERE match_id = %d AND (voter_hash = %s OR client_hash = %s) LIMIT 1",
            $match_id,
            $voter_hash,
            $client_hash
        ));
        if ($already) {
            return new WP_Error('already_voted', 'You have already voted in this match');
        }

        $suppress = $wpdb->suppress_errors(true);
        $inserted = $wpdb->insert(
            $ledger,
            [
                'match_id' => $match_id,
                'performer_id' => $performer_id,
                'voter_hash' => $voter_hash,
                'client_hash' => $client_hash !== '' ? $client_hash : null,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s']
        );
        $wpdb->suppress_errors($suppress);

        if (!$inserted) {
            // Lost a race to a concurrent vote from the same voter/client.
            return new WP_Error('already_voted', 'You have already voted in this match');
        }

        $ledger_id = (int) $wpdb->insert_id;
        $column = $performer_id === (int) $match->performer_1_id ? 'votes_performer_1' : 'votes_performer_2';

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE $matches SET $column = $column + 1, updated_at = %s WHERE id = %d AND status = %s",
            current_time('mysql'),
            $match_id,
            self::MATCH_VOTING
        ));

        if (!$updated) {
            // Voting closed between the checks and the increment: undo the ledger row.
            $wpdb->delete($ledger, ['id' => $ledger_id], ['%d']);
            return new WP_Error('voting_closed', 'Voting is not open for this match');
        }

        /**
         * Fires when a match vote is submitted.
         *
         * @since 1.1.0
         *
         * @param int    $match_id     Match ID.
         * @param int    $performer_id Performer voted for.
         * @param string $voter_hash   Server-derived voter identifier.
         */
        do_action('peanut_festival_match_vote_submitted', $match_id, $performer_id, $voter_hash);

        return true;
    }

    /**
     * Complete a match and determine winner.
     *
     * @since 1.1.0
     * @since 1.4.0 Added double elimination support with loser tracking.
     *
     * @param int      $match_id Match ID.
     * @param int|null $winner_id Optional winner ID (if not using votes).
     * @return bool Success.
     */
    public static function complete_match(int $match_id, ?int $winner_id = null): bool {
        $match = self::get_match($match_id);
        if (!$match) {
            return false;
        }

        // Determine winner by votes if not specified
        if (!$winner_id) {
            if ($match->votes_performer_1 > $match->votes_performer_2) {
                $winner_id = $match->performer_1_id;
            } elseif ($match->votes_performer_2 > $match->votes_performer_1) {
                $winner_id = $match->performer_2_id;
            } else {
                // Tie - could implement tiebreaker logic here
                // For now, performer 1 wins ties (higher seed usually)
                $winner_id = $match->performer_1_id;
            }
        }

        // Determine loser (for double elimination)
        $loser_id = ($winner_id == $match->performer_1_id)
            ? $match->performer_2_id
            : $match->performer_1_id;

        self::update_match($match_id, [
            'winner_id' => $winner_id,
            'loser_id' => $loser_id,
            'status' => self::MATCH_COMPLETED,
            'completed_at' => current_time('mysql'),
        ]);

        // Refresh match to get updated data
        $match = self::get_match($match_id);

        // Advance winner to next round (handles both single and double elimination)
        self::advance_winner($match_id, $winner_id, $loser_id);

        // Fire hook for Booker integration
        Peanut_Festival_Booker_Integration::fire_vote_winner(
            $winner_id,
            0, // No show_id for competition matches
            $winner_id == $match->performer_1_id ? $match->votes_performer_1 : $match->votes_performer_2
        );

        return true;
    }

    /**
     * Advance winner to next round.
     *
     * Routes to appropriate advancement logic based on competition type.
     *
     * @since 1.1.0
     * @since 1.4.0 Added double elimination support.
     *
     * @param int      $match_id Completed match ID.
     * @param int      $winner_id Winner performer ID.
     * @param int|null $loser_id Loser performer ID (for double elimination).
     */
    private static function advance_winner(int $match_id, int $winner_id, ?int $loser_id = null): void {
        $match = self::get_match($match_id);
        if (!$match) {
            return;
        }

        $competition = self::get_by_id($match->competition_id);
        if (!$competition) {
            return;
        }

        // Route based on competition type
        switch ($competition->competition_type) {
            case self::TYPE_ROUND_ROBIN:
                // Round robin doesn't advance
                return;

            case self::TYPE_DOUBLE_ELIMINATION:
                // Use double elimination advancement logic
                self::advance_double_elim_winner($match, $winner_id, $loser_id);
                return;

            case self::TYPE_SINGLE_ELIMINATION:
            default:
                // Use single elimination advancement logic
                self::advance_single_elim_winner($match, $winner_id, $competition);
                return;
        }
    }

    /**
     * Advance winner in single elimination bracket.
     *
     * @since 1.4.0
     *
     * @param object $match Current match.
     * @param int    $winner_id Winner performer ID.
     * @param object $competition Competition object.
     */
    private static function advance_single_elim_winner(object $match, int $winner_id, object $competition): void {
        // Find next round match
        $next_round = $match->round_number + 1;
        $next_match_number = (int) ceil($match->match_number / 2);

        $next_match = Peanut_Festival_Database::get_row('competition_matches', [
            'competition_id' => $match->competition_id,
            'round_number' => $next_round,
            'match_number' => $next_match_number,
        ]);

        if (!$next_match) {
            // This was the final - update competition winner
            if ($next_round > $competition->rounds_count) {
                self::update($competition->id, [
                    'winner_performer_id' => $winner_id,
                    'status' => self::STATUS_COMPLETED,
                    'completed_at' => current_time('mysql'),
                ]);

                /** This action is documented in class-competitions.php */
                do_action('peanut_festival_competition_completed', $competition->id, $winner_id);
            }
            return;
        }

        // Determine position in next match (top or bottom)
        $is_top = ($match->match_number % 2) === 1;

        $update_data = $is_top
            ? ['performer_1_id' => $winner_id]
            : ['performer_2_id' => $winner_id];

        self::update_match($next_match->id, $update_data);
    }

    /**
     * Check and complete matches past their deadline.
     *
     * @since 1.1.0
     */
    public function check_match_deadlines(): void {
        global $wpdb;
        $table = Peanut_Festival_Database::get_table_name('competition_matches');

        $overdue = $wpdb->get_results(
            "SELECT id FROM $table
             WHERE status = 'voting'
             AND voting_closes_at < NOW()"
        );

        foreach ($overdue as $match) {
            self::complete_match($match->id);
        }
    }

    // =========================================================================
}
