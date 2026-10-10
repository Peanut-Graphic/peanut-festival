<?php
/**
 * Voting system class (ported from mcf-voting)
 *
 * @package Peanut_Festival
 */

if (!defined('ABSPATH')) {
    exit;
}

class Peanut_Festival_Voting {

    private static ?Peanut_Festival_Voting $instance = null;

    /**
     * Cache expiration in seconds (30 seconds for active voting)
     */
    private const CACHE_EXPIRATION = 30;

    /**
     * Maximum ranked choices on a show-vote ballot: one per weighted rank
     * (weight_first, weight_second, weight_third).
     */
    public const MAX_BALLOT_RANKS = 3;

    /**
     * Upper bound on the raw performer_ids list accepted for validation.
     */
    private const MAX_RAW_BALLOT_ENTRIES = 20;

    public static function get_instance(): Peanut_Festival_Voting {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Get voting configuration for a show (with caching)
     *
     * @param string $show_slug The show slug
     * @param bool $bypass_cache Force fresh data from database
     * @return array The voting configuration
     */
    public static function get_show_config(string $show_slug, bool $bypass_cache = false): array {
        $cache_key = 'pf_voting_cfg_' . md5($show_slug);

        // Try to get from transient cache first
        if (!$bypass_cache) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        $config = get_option("pf_voting_config_{$show_slug}", []);

        $defaults = [
            'groups' => [],
            'pool' => [],
            'active_group' => 'pool',
            'timer_start' => null,
            'timer_duration' => 0,
            'num_groups' => 3,
            'top_per_group' => 2,
            'weight_first' => 3,
            'weight_second' => 2,
            'weight_third' => 1,
            'hide_bios' => false,
            'reveal_results' => false,
            // Vote verification options (Phase 2)
            'verification_mode' => 'none', // none, email, sms, captcha
            'require_email' => false,
            'one_vote_per_email' => false,
            'require_captcha' => false,
            'device_fingerprint' => true,
            'rate_limit_votes' => 5, // max votes per minute per IP
        ];

        $config = wp_parse_args($config, $defaults);

        // Cache for 30 seconds during active voting
        set_transient($cache_key, $config, self::CACHE_EXPIRATION);

        return $config;
    }

    /**
     * Save voting configuration for a show
     *
     * @param string $show_slug The show slug
     * @param array $config The configuration to save
     * @return bool Success
     */
    public static function save_show_config(string $show_slug, array $config): bool {
        // Clear the cache when config is updated
        $cache_key = 'pf_voting_cfg_' . md5($show_slug);
        delete_transient($cache_key);

        return update_option("pf_voting_config_{$show_slug}", $config);
    }

    /**
     * Clear voting config cache for a show
     *
     * @param string $show_slug The show slug
     */
    public static function clear_config_cache(string $show_slug): void {
        $cache_key = 'pf_voting_cfg_' . md5($show_slug);
        delete_transient($cache_key);
    }

    public static function is_voting_open(string $show_slug): bool {
        $config = self::get_show_config($show_slug);

        if ($config['active_group'] === 'pool') {
            return false;
        }

        if ($config['timer_duration'] > 0 && $config['timer_start']) {
            $start = strtotime($config['timer_start']);
            $end = $start + ($config['timer_duration'] * 60);
            $now = current_time('timestamp');

            return $now < $end;
        }

        return true;
    }

    public static function get_time_remaining(string $show_slug): ?int {
        $config = self::get_show_config($show_slug);

        if (!$config['timer_start'] || $config['timer_duration'] <= 0) {
            return null;
        }

        $start = strtotime($config['timer_start']);
        $end = $start + ($config['timer_duration'] * 60);
        $now = current_time('timestamp');

        $remaining = $end - $now;
        return max(0, $remaining);
    }

    public static function has_voted(string $show_slug, string $group_name, string $token, string $ip_hash): bool {
        global $wpdb;
        $table = Peanut_Festival_Database::get_table_name('votes');

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE show_slug = %s AND group_name = %s AND (token = %s OR ip_hash = %s)",
            $show_slug, $group_name, $token, $ip_hash
        ));

        return (int) $count > 0;
    }

    public static function record_vote(array $data): int|false {
        $vote_data = [
            'show_slug' => $data['show_slug'],
            'group_name' => $data['group_name'],
            'performer_id' => $data['performer_id'],
            'vote_rank' => $data['vote_rank'] ?? 1,
            'ip_hash' => $data['ip_hash'],
            'ua_hash' => $data['ua_hash'],
            'token' => $data['token'],
            'voted_at' => current_time('mysql'),
        ];

        // Add fingerprint hash if provided (for fraud detection)
        if (!empty($data['fingerprint_hash'])) {
            $vote_data['fingerprint_hash'] = $data['fingerprint_hash'];
        }

        // One-ballot-per-voter key (UNIQUE with vote_rank); NULL for admin test votes.
        if (!empty($data['ballot_key'])) {
            $vote_data['ballot_key'] = $data['ballot_key'];
        }

        return Peanut_Festival_Database::insert('votes', $vote_data);
    }

    /**
     * Validate and normalise a submitted ballot.
     *
     * A ballot is an ordered list of distinct performer ids, all in the show's
     * active group, at most MAX_BALLOT_RANKS long. Repeats of a performer are
     * collapsed to their first (highest) rank; anything else invalid rejects the
     * whole ballot.
     *
     * @param mixed $performer_ids Raw performer_ids parameter.
     * @param array $config        Show voting config (see get_show_config()).
     * @return int[]|WP_Error Ordered performer ids, or an error.
     */
    public static function normalize_ballot($performer_ids, array $config): array|WP_Error {
        if (!is_array($performer_ids) || empty($performer_ids)) {
            return new WP_Error('invalid_ballot', 'Select at least one performer.');
        }

        if (count($performer_ids) > self::MAX_RAW_BALLOT_ENTRIES) {
            return new WP_Error('invalid_ballot', 'Too many selections.');
        }

        $group = (string) ($config['active_group'] ?? 'pool');
        $eligible = array_map('intval', (array) ($config['groups'][$group] ?? []));

        $ballot = [];
        foreach (array_values($performer_ids) as $raw) {
            if (!is_int($raw) && !(is_string($raw) && ctype_digit($raw))) {
                return new WP_Error('invalid_ballot', 'Invalid performer selection.');
            }

            $performer_id = (int) $raw;
            if ($performer_id <= 0 || !in_array($performer_id, $eligible, true)) {
                return new WP_Error('invalid_ballot', 'One or more selected performers are not in the current voting group.');
            }

            if (!in_array($performer_id, $ballot, true)) {
                $ballot[] = $performer_id;
            }
        }

        if (count($ballot) > self::MAX_BALLOT_RANKS) {
            return new WP_Error(
                'invalid_ballot',
                sprintf('Select at most %d performers.', self::MAX_BALLOT_RANKS)
            );
        }

        return $ballot;
    }

    /**
     * Keyed identifier for one voter's ballot in one show group.
     */
    public static function ballot_key(string $show_slug, string $group_name, string $ip_hash): string {
        return hash_hmac('sha256', 'ballot|' . $show_slug . '|' . $group_name . '|' . $ip_hash, wp_salt('auth'));
    }

    /**
     * Record a validated ballot, one row per rank.
     *
     * With a ballot key, the UNIQUE (ballot_key, vote_rank) index makes the
     * rank-1 insert the atomic "first ballot wins" point; if any later rank
     * fails, the rows already written for this ballot are removed.
     *
     * @param array       $base       Common vote fields (show_slug, group_name, ip_hash, ua_hash, token, fingerprint_hash).
     * @param int[]       $ballot     Output of normalize_ballot().
     * @param string|null $ballot_key Voter ballot key, or null for unrestricted admin test votes.
     * @return true|WP_Error
     */
    public static function record_ballot(array $base, array $ballot, ?string $ballot_key): bool|WP_Error {
        global $wpdb;

        $written = [];

        foreach (array_values($ballot) as $index => $performer_id) {
            $row = $base;
            $row['performer_id'] = (int) $performer_id;
            $row['vote_rank'] = $index + 1;
            $row['ballot_key'] = $ballot_key;

            $suppress = $wpdb->suppress_errors(true);
            $id = self::record_vote($row);
            $error = (string) $wpdb->last_error;
            $wpdb->suppress_errors($suppress);

            if (!$id) {
                foreach ($written as $written_id) {
                    Peanut_Festival_Database::delete('votes', ['id' => $written_id]);
                }

                if ($index === 0 && stripos($error, 'Duplicate') !== false) {
                    return new WP_Error('already_voted', 'You have already voted');
                }

                return new WP_Error('vote_failed', 'Your vote could not be recorded. Please try again.');
            }

            $written[] = (int) $id;
        }

        return true;
    }

    public static function get_results(string $show_slug, string $group_name = ''): array {
        global $wpdb;
        $votes_table = Peanut_Festival_Database::get_table_name('votes');
        $performers_table = Peanut_Festival_Database::get_table_name('performers');

        $config = self::get_show_config($show_slug);

        $sql = "SELECT
                    v.performer_id,
                    p.name as performer_name,
                    p.photo_url,
                    v.group_name,
                    SUM(CASE WHEN v.vote_rank = 1 THEN 1 ELSE 0 END) as first_place,
                    SUM(CASE WHEN v.vote_rank = 2 THEN 1 ELSE 0 END) as second_place,
                    SUM(CASE WHEN v.vote_rank = 3 THEN 1 ELSE 0 END) as third_place,
                    COUNT(*) as total_votes,
                    SUM(
                        CASE v.vote_rank
                            WHEN 1 THEN %d
                            WHEN 2 THEN %d
                            WHEN 3 THEN %d
                            ELSE 0
                        END
                    ) as weighted_score
                FROM $votes_table v
                LEFT JOIN $performers_table p ON v.performer_id = p.id
                WHERE v.show_slug = %s";

        $values = [
            $config['weight_first'],
            $config['weight_second'],
            $config['weight_third'],
            $show_slug,
        ];

        if ($group_name) {
            $sql .= " AND v.group_name = %s";
            $values[] = $group_name;
        }

        $sql .= " GROUP BY v.performer_id, v.group_name, p.name, p.photo_url
                  ORDER BY weighted_score DESC, first_place DESC";

        return $wpdb->get_results($wpdb->prepare($sql, ...$values));
    }

    public static function get_vote_logs(string $show_slug = '', int $limit = 200): array {
        global $wpdb;
        $votes_table = Peanut_Festival_Database::get_table_name('votes');
        $performers_table = Peanut_Festival_Database::get_table_name('performers');

        $sql = "SELECT v.*, p.name as performer_name
                FROM $votes_table v
                LEFT JOIN $performers_table p ON v.performer_id = p.id";

        $values = [];

        if ($show_slug) {
            $sql .= " WHERE v.show_slug = %s";
            $values[] = $show_slug;
        }

        $sql .= " ORDER BY v.voted_at DESC LIMIT %d";
        $values[] = $limit;

        return $wpdb->get_results($wpdb->prepare($sql, ...$values));
    }

    public static function calculate_finals(string $show_slug): array {
        $config = self::get_show_config($show_slug);
        $results = self::get_results($show_slug);

        if (empty($results)) {
            return [];
        }

        // Group results by group_name
        $grouped = [];
        foreach ($results as $row) {
            $grouped[$row->group_name][] = $row;
        }

        // Calculate normalized scores
        $all_results = [];
        $group_count = count($grouped);

        if ($group_count === 0) {
            return [];
        }

        // Get average votes across all groups
        $total_votes_all = 0;
        $group_sizes = [];

        foreach ($grouped as $group_name => $group_results) {
            $group_votes = 0;
            foreach ($group_results as $r) {
                $group_votes += $r->total_votes;
            }
            $group_sizes[$group_name] = [
                'count' => count($group_results),
                'votes' => $group_votes,
            ];
            $total_votes_all += $group_votes;
        }

        $avg_votes = $group_count > 0 ? $total_votes_all / $group_count : 1;

        foreach ($grouped as $group_name => $group_results) {
            $group_size = $group_sizes[$group_name]['count'];
            $group_votes = $group_sizes[$group_name]['votes'];

            foreach ($group_results as $r) {
                $raw_score = (float) $r->weighted_score;

                // Normalize: (raw / group_size) * (avg_votes / group_votes)
                $normalized = $group_size > 0 && $group_votes > 0
                    ? ($raw_score / $group_size) * ($avg_votes / $group_votes)
                    : $raw_score;

                $all_results[] = [
                    'performer_id' => $r->performer_id,
                    'performer_name' => $r->performer_name,
                    'photo_url' => $r->photo_url,
                    'group_name' => $group_name,
                    'raw_score' => $raw_score,
                    'normalized_score' => round($normalized, 2),
                    'first_place_votes' => (int) $r->first_place,
                    'second_place_votes' => (int) $r->second_place,
                    'total_votes' => (int) $r->total_votes,
                ];
            }
        }

        // Sort by normalized score, then first place votes (tie breaker)
        usort($all_results, function($a, $b) {
            if ($a['normalized_score'] !== $b['normalized_score']) {
                return $b['normalized_score'] <=> $a['normalized_score'];
            }
            return $b['first_place_votes'] <=> $a['first_place_votes'];
        });

        // Assign ranks
        $rank = 1;
        foreach ($all_results as &$result) {
            $result['final_rank'] = $rank++;
        }

        // Store in finals table
        self::save_finals($show_slug, $all_results);

        return $all_results;
    }

    private static function save_finals(string $show_slug, array $results): void {
        global $wpdb;
        $table = Peanut_Festival_Database::get_table_name('voting_finals');

        // Clear existing finals for this show
        $wpdb->delete($table, ['show_slug' => $show_slug]);

        foreach ($results as $result) {
            Peanut_Festival_Database::insert('voting_finals', [
                'show_slug' => $show_slug,
                'performer_id' => $result['performer_id'],
                'group_name' => $result['group_name'],
                'raw_score' => $result['raw_score'],
                'normalized_score' => $result['normalized_score'],
                'final_rank' => $result['final_rank'],
                'first_place_votes' => $result['first_place_votes'],
                'second_place_votes' => $result['second_place_votes'],
                'total_votes' => $result['total_votes'],
                'calculated_at' => current_time('mysql'),
            ]);
        }
    }

    public static function clear_votes(string $show_slug, string $group_name = ''): int|false {
        global $wpdb;
        $table = Peanut_Festival_Database::get_table_name('votes');

        if ($group_name) {
            return $wpdb->delete($table, [
                'show_slug' => $show_slug,
                'group_name' => $group_name,
            ]);
        }

        return $wpdb->delete($table, ['show_slug' => $show_slug]);
    }

    public static function hash_ip(string $ip): string {
        return hash('sha256', $ip . NONCE_SALT);
    }

    public static function hash_ua(string $ua): string {
        return hash('sha256', $ua . NONCE_SALT);
    }

    /**
     * Generate a device fingerprint hash from multiple browser/device attributes
     * This provides better fraud detection than IP/UA alone
     *
     * @param array $fingerprint_data Device fingerprint data from frontend
     * @return string Hashed fingerprint
     */
    public static function generate_device_fingerprint(array $fingerprint_data): string {
        // Normalize and sort the fingerprint components for consistent hashing
        $components = [
            'screen' => ($fingerprint_data['screen_width'] ?? '') . 'x' . ($fingerprint_data['screen_height'] ?? ''),
            'color_depth' => $fingerprint_data['color_depth'] ?? '',
            'timezone' => $fingerprint_data['timezone'] ?? '',
            'language' => $fingerprint_data['language'] ?? '',
            'platform' => $fingerprint_data['platform'] ?? '',
            'touch' => $fingerprint_data['touch_support'] ?? '',
            'webgl' => substr($fingerprint_data['webgl_vendor'] ?? '', 0, 50), // Truncate for consistency
            'canvas' => $fingerprint_data['canvas_hash'] ?? '',
        ];

        ksort($components);
        $fingerprint_string = implode('|', $components);

        return hash('sha256', $fingerprint_string . NONCE_SALT);
    }

    /**
     * Detect suspicious voting patterns
     *
     * @param string $show_slug Show identifier
     * @param string $ip_hash Hashed IP address
     * @param string $fingerprint_hash Device fingerprint hash
     * @return array Suspicion indicators and confidence score
     */
    public static function detect_vote_fraud(string $show_slug, string $ip_hash, string $fingerprint_hash = ''): array {
        global $wpdb;
        $table = Peanut_Festival_Database::get_table_name('votes');
        $suspicion_score = 0;
        $indicators = [];

        // Check for rapid voting from same IP (more than 3 in 30 seconds)
        $rapid_votes = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table
             WHERE show_slug = %s AND ip_hash = %s
             AND voted_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)",
            $show_slug, $ip_hash
        ));

        if ((int) $rapid_votes >= 3) {
            $suspicion_score += 40;
            $indicators[] = 'rapid_voting';
        }

        // Check for multiple different tokens from same IP in short time
        $unique_tokens = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT token) FROM $table
             WHERE show_slug = %s AND ip_hash = %s
             AND voted_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
            $show_slug, $ip_hash
        ));

        if ((int) $unique_tokens >= 5) {
            $suspicion_score += 30;
            $indicators[] = 'multiple_tokens';
        }

        // Check for same fingerprint across different IPs (if fingerprint provided)
        if (!empty($fingerprint_hash)) {
            $different_ips = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT ip_hash) FROM $table
                 WHERE show_slug = %s AND fingerprint_hash = %s
                 AND voted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                $show_slug, $fingerprint_hash
            ));

            if ((int) $different_ips >= 3) {
                $suspicion_score += 50;
                $indicators[] = 'fingerprint_ip_mismatch';
            }
        }

        // Check for sequential voting patterns (votes at exact intervals)
        $vote_times = $wpdb->get_col($wpdb->prepare(
            "SELECT UNIX_TIMESTAMP(voted_at) FROM $table
             WHERE show_slug = %s AND ip_hash = %s
             ORDER BY voted_at DESC LIMIT 10",
            $show_slug, $ip_hash
        ));

        if (count($vote_times) >= 5) {
            $intervals = [];
            for ($i = 0; $i < count($vote_times) - 1; $i++) {
                $intervals[] = abs($vote_times[$i] - $vote_times[$i + 1]);
            }
            // Check if intervals are suspiciously consistent (within 2 seconds)
            $avg_interval = array_sum($intervals) / count($intervals);
            $variance = 0;
            foreach ($intervals as $interval) {
                $variance += pow($interval - $avg_interval, 2);
            }
            $std_dev = sqrt($variance / count($intervals));

            if ($std_dev < 2 && $avg_interval < 10) {
                $suspicion_score += 60;
                $indicators[] = 'automated_pattern';
            }
        }

        return [
            'is_suspicious' => $suspicion_score >= 50,
            'score' => min(100, $suspicion_score),
            'indicators' => $indicators,
        ];
    }

    /**
     * Log suspicious voting activity for review
     *
     * @param array $vote_data Vote data
     * @param array $fraud_result Fraud detection result
     */
    public static function log_suspicious_vote(array $vote_data, array $fraud_result): void {
        if (!$fraud_result['is_suspicious']) {
            return;
        }

        Peanut_Festival_Logger::warning('Suspicious voting activity detected', [
            'show_slug' => $vote_data['show_slug'] ?? '',
            'group_name' => $vote_data['group_name'] ?? '',
            'ip_hash' => substr($vote_data['ip_hash'] ?? '', 0, 16) . '...',
            'suspicion_score' => $fraud_result['score'],
            'indicators' => $fraud_result['indicators'],
        ]);
    }

    // =========================================
    // Vote Verification Methods (Phase 2)
    // =========================================

    /**
     * Check if rate limit has been exceeded for an IP
     */
    public static function check_rate_limit(string $show_slug, string $ip_hash): bool {
        $config = self::get_show_config($show_slug);
        $max_votes = $config['rate_limit_votes'] ?? 5;

        global $wpdb;
        $table = Peanut_Festival_Database::get_table_name('votes');

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table
             WHERE show_slug = %s
             AND ip_hash = %s
             AND voted_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            $show_slug,
            $ip_hash
        ));

        return (int) $count < $max_votes;
    }

    /**
     * Check if email has already been used to vote (when one_vote_per_email is enabled)
     */
    public static function has_email_voted(string $show_slug, string $group_name, string $email): bool {
        $email_hash = hash('sha256', strtolower(trim($email)) . NONCE_SALT);

        global $wpdb;
        $table = Peanut_Festival_Database::get_table_name('votes');

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table
             WHERE show_slug = %s
             AND group_name = %s
             AND email_hash = %s",
            $show_slug,
            $group_name,
            $email_hash
        ));

        return (int) $count > 0;
    }

    /**
     * Generate email verification code
     */
    public static function generate_email_verification(string $email, string $show_slug): string {
        $code = wp_generate_password(6, false, false);
        $email_hash = hash('sha256', strtolower(trim($email)) . NONCE_SALT);

        set_transient(
            'pf_vote_verify_' . $email_hash . '_' . $show_slug,
            $code,
            15 * MINUTE_IN_SECONDS
        );

        return $code;
    }

    /**
     * Verify email code
     */
    public static function verify_email_code(string $email, string $show_slug, string $code): bool {
        $email_hash = hash('sha256', strtolower(trim($email)) . NONCE_SALT);
        $stored_code = get_transient('pf_vote_verify_' . $email_hash . '_' . $show_slug);

        if (!$stored_code) {
            return false;
        }

        if ($stored_code !== strtoupper($code)) {
            return false;
        }

        // Delete the transient after successful verification
        delete_transient('pf_vote_verify_' . $email_hash . '_' . $show_slug);
        return true;
    }

    /**
     * Send verification email
     */
    public static function send_verification_email(string $email, string $code, string $show_name = ''): bool {
        $subject = 'Your Voting Verification Code';
        $message = sprintf(
            "Your verification code is: %s\n\n" .
            "This code will expire in 15 minutes.\n\n" .
            "%s",
            $code,
            $show_name ? "You are voting for: {$show_name}" : ''
        );

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        return wp_mail($email, $subject, $message, $headers);
    }

    /**
     * Generate device fingerprint hash
     */
    public static function generate_fingerprint(array $data): string {
        $fp_data = [
            'ua' => $data['user_agent'] ?? '',
            'screen' => $data['screen_resolution'] ?? '',
            'tz' => $data['timezone'] ?? '',
            'lang' => $data['language'] ?? '',
            'platform' => $data['platform'] ?? '',
        ];

        return hash('sha256', json_encode($fp_data) . NONCE_SALT);
    }

    /**
     * Check if fingerprint has already voted
     */
    public static function has_fingerprint_voted(string $show_slug, string $group_name, string $fingerprint): bool {
        global $wpdb;
        $table = Peanut_Festival_Database::get_table_name('votes');

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table
             WHERE show_slug = %s
             AND group_name = %s
             AND device_fingerprint = %s",
            $show_slug,
            $group_name,
            $fingerprint
        ));

        return (int) $count > 0;
    }

    /**
     * Validate vote with all configured verification checks
     */
    public static function validate_vote(string $show_slug, string $group_name, array $vote_data): array {
        $config = self::get_show_config($show_slug);
        $errors = [];

        // Check rate limit
        $ip_hash = self::hash_ip($vote_data['ip'] ?? '');
        if (!self::check_rate_limit($show_slug, $ip_hash)) {
            $errors[] = 'Rate limit exceeded. Please wait before voting again.';
        }

        // Check token/IP duplicate
        if (self::has_voted($show_slug, $group_name, $vote_data['token'] ?? '', $ip_hash)) {
            $errors[] = 'You have already voted in this round.';
        }

        // Check email verification if required
        if ($config['require_email'] && empty($vote_data['email'])) {
            $errors[] = 'Email address is required.';
        }

        if ($config['one_vote_per_email'] && !empty($vote_data['email'])) {
            if (self::has_email_voted($show_slug, $group_name, $vote_data['email'])) {
                $errors[] = 'This email address has already been used to vote.';
            }
        }

        // Check device fingerprint if enabled
        if ($config['device_fingerprint'] && !empty($vote_data['fingerprint'])) {
            if (self::has_fingerprint_voted($show_slug, $group_name, $vote_data['fingerprint'])) {
                $errors[] = 'This device has already voted.';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
