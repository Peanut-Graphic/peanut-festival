<?php
/**
 * Competitions REST Endpoints Trait
 *
 * @package    Peanut_Festival
 * @subpackage Includes/Competitions
 * @since      1.2.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait Peanut_Festival_Competitions_REST_Endpoints
 *
 * Handles REST API routes and callbacks for competitions.
 *
 * @since 1.2.1
 */
trait Peanut_Festival_Competitions_REST_Endpoints {

    /**
     * Register REST routes.
     *
     * @since 1.1.0
     */
    public function register_rest_routes(): void {
        // Public endpoints
        register_rest_route('peanut-festival/v1', '/competitions', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_competitions'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('peanut-festival/v1', '/competitions/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_competition'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('peanut-festival/v1', '/competitions/(?P<id>\d+)/bracket', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_bracket'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('peanut-festival/v1', '/matches/(?P<id>\d+)/vote', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_submit_vote'],
            'permission_callback' => '__return_true',
        ]);

        // Admin endpoints
        register_rest_route('peanut-festival/v1/admin', '/competitions', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_create_competition'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route('peanut-festival/v1/admin', '/competitions/(?P<id>\d+)', [
            'methods' => 'PATCH',
            'callback' => [$this, 'rest_update_competition'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route('peanut-festival/v1/admin', '/competitions/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'rest_delete_competition'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route('peanut-festival/v1/admin', '/competitions/(?P<id>\d+)/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_generate_bracket'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route('peanut-festival/v1/admin', '/matches/(?P<id>\d+)/start', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_start_voting'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route('peanut-festival/v1/admin', '/matches/(?P<id>\d+)/complete', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_complete_match'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);
    }

    /**
     * Check admin permission.
     *
     * @since 1.1.0
     *
     * @return bool
     */
    public function check_admin_permission(): bool {
        return current_user_can('manage_options');
    }

    /**
     * REST: Get competitions.
     *
     * @since 1.1.0
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function rest_get_competitions(WP_REST_Request $request): WP_REST_Response {
        $competitions = self::get_all([
            'festival_id' => $request->get_param('festival_id'),
            'status' => $request->get_param('status'),
        ]);

        return new WP_REST_Response(['competitions' => $competitions]);
    }

    /**
     * REST: Get single competition.
     *
     * @since 1.1.0
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function rest_get_competition(WP_REST_Request $request): WP_REST_Response {
        $competition = self::get_by_id((int) $request->get_param('id'));

        if (!$competition) {
            return new WP_REST_Response(['error' => 'Competition not found'], 404);
        }

        $competition->matches = self::get_matches($competition->id);

        return new WP_REST_Response(['competition' => $competition]);
    }

    /**
     * REST: Get bracket.
     *
     * @since 1.1.0
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function rest_get_bracket(WP_REST_Request $request): WP_REST_Response {
        $bracket = self::get_bracket((int) $request->get_param('id'));

        return new WP_REST_Response(['bracket' => $bracket]);
    }

    /**
     * REST: Submit vote.
     *
     * @since 1.1.0
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function rest_submit_vote(WP_REST_Request $request): WP_REST_Response {
        // Only this site's pages may submit votes.
        $origin_error = Peanut_Festival_Request_Guard::require_same_origin($request, 'match_vote');
        if ($origin_error !== null) {
            return $origin_error;
        }

        // Per-IP budget, counted before any other work.
        $rate_limit = Peanut_Festival_Rate_Limiter::enforce('match_vote');
        if ($rate_limit !== null) {
            return $rate_limit;
        }

        $match_id = (int) $request->get_param('id');
        $performer_id = (int) $request->get_param('performer_id');

        // The voter is derived server-side; any client-supplied `voter_id` is ignored.
        $result = self::submit_match_vote(
            $match_id,
            $performer_id,
            Peanut_Festival_Voter_Identity::voter_hash(),
            Peanut_Festival_Voter_Identity::client_hash()
        );

        if (is_wp_error($result)) {
            return new WP_REST_Response([
                'error' => $result->get_error_message(),
                'code' => $result->get_error_code(),
            ], 400);
        }

        return new WP_REST_Response(['success' => true]);
    }

    /**
     * REST: Create competition.
     *
     * @since 1.1.0
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function rest_create_competition(WP_REST_Request $request): WP_REST_Response {
        $data = [
            'festival_id' => (int) $request->get_param('festival_id'),
            'name' => sanitize_text_field($request->get_param('name')),
            'description' => sanitize_textarea_field($request->get_param('description') ?? ''),
            'competition_type' => sanitize_key($request->get_param('competition_type') ?? self::TYPE_SINGLE_ELIMINATION),
            'voting_method' => sanitize_key($request->get_param('voting_method') ?? self::VOTING_HEAD_TO_HEAD),
            'voting_duration' => (int) ($request->get_param('voting_duration') ?? 10),
            'status' => self::STATUS_SETUP,
        ];

        $id = self::create($data);

        if (!$id) {
            return new WP_REST_Response(['error' => 'Failed to create competition'], 500);
        }

        return new WP_REST_Response([
            'success' => true,
            'id' => $id,
            'competition' => self::get_by_id($id),
        ]);
    }

    /**
     * REST: Update competition.
     *
     * @since 1.1.0
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function rest_update_competition(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $data = [];

        $allowed = ['name', 'description', 'status', 'voting_duration', 'voting_method'];
        foreach ($allowed as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $data[$field] = is_string($value) ? sanitize_text_field($value) : $value;
            }
        }

        $result = self::update($id, $data);

        return new WP_REST_Response([
            'success' => $result !== false,
            'competition' => self::get_by_id($id),
        ]);
    }

    /**
     * REST: Delete competition.
     *
     * @since 1.1.0
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function rest_delete_competition(WP_REST_Request $request): WP_REST_Response {
        $result = self::delete((int) $request->get_param('id'));

        return new WP_REST_Response(['success' => $result !== false]);
    }

    /**
     * REST: Generate bracket.
     *
     * @since 1.1.0
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function rest_generate_bracket(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $performer_ids = $request->get_param('performer_ids');

        if (!is_array($performer_ids) || count($performer_ids) < 2) {
            return new WP_REST_Response(['error' => 'At least 2 performers required'], 400);
        }

        $result = self::generate_bracket($id, array_map('intval', $performer_ids));

        if (!$result) {
            return new WP_REST_Response(['error' => 'Failed to generate bracket'], 500);
        }

        return new WP_REST_Response([
            'success' => true,
            'bracket' => self::get_bracket($id),
        ]);
    }

    /**
     * REST: Start match voting.
     *
     * @since 1.1.0
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function rest_start_voting(WP_REST_Request $request): WP_REST_Response {
        $match_id = (int) $request->get_param('id');
        $duration = (int) ($request->get_param('duration') ?? 10);

        $result = self::start_match_voting($match_id, $duration);

        return new WP_REST_Response(['success' => $result]);
    }

    /**
     * REST: Complete match.
     *
     * @since 1.1.0
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function rest_complete_match(WP_REST_Request $request): WP_REST_Response {
        $match_id = (int) $request->get_param('id');
        $winner_id = $request->get_param('winner_id') ? (int) $request->get_param('winner_id') : null;

        $result = self::complete_match($match_id, $winner_id);

        return new WP_REST_Response(['success' => $result]);
    }
}
