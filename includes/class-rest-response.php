<?php
/**
 * REST API Response Helper
 *
 * Provides standardized response formats for all API endpoints.
 *
 * @package Peanut_Festival
 */

if (!defined('ABSPATH')) {
    exit;
}

class Peanut_Festival_REST_Response {

    /**
     * Error codes and their HTTP status codes
     */
    private const ERROR_CODES = [
        // 400 Bad Request
        'missing_parameters' => ['status' => 400, 'message' => 'Missing required parameters'],
        'invalid_parameters' => ['status' => 400, 'message' => 'Invalid parameter values'],
        'invalid_email' => ['status' => 400, 'message' => 'Invalid email address'],
        'invalid_date' => ['status' => 400, 'message' => 'Invalid date format'],
        'invalid_amount' => ['status' => 400, 'message' => 'Invalid amount'],
        'empty_data' => ['status' => 400, 'message' => 'No data provided'],
        'invalid_ballot' => ['status' => 400, 'message' => 'Invalid ballot'],

        // 401 Unauthorized
        'unauthorized' => ['status' => 401, 'message' => 'Authentication required'],
        'invalid_token' => ['status' => 401, 'message' => 'Invalid or expired token'],

        // 403 Forbidden
        'forbidden' => ['status' => 403, 'message' => 'Access denied'],
        'voting_closed' => ['status' => 403, 'message' => 'Voting is closed'],
        'already_voted' => ['status' => 403, 'message' => 'You have already voted'],
        'already_applied' => ['status' => 403, 'message' => 'An application already exists'],
        'capacity_reached' => ['status' => 403, 'message' => 'Capacity limit reached'],

        // 404 Not Found
        'not_found' => ['status' => 404, 'message' => 'Resource not found'],
        'festival_not_found' => ['status' => 404, 'message' => 'Festival not found'],
        'show_not_found' => ['status' => 404, 'message' => 'Show not found'],
        'performer_not_found' => ['status' => 404, 'message' => 'Performer not found'],
        'ticket_not_found' => ['status' => 404, 'message' => 'Ticket not found'],
        'volunteer_not_found' => ['status' => 404, 'message' => 'Volunteer not found'],

        // 409 Conflict
        'duplicate_entry' => ['status' => 409, 'message' => 'Resource already exists'],
        'email_exists' => ['status' => 409, 'message' => 'Email already registered'],

        // 422 Unprocessable Entity
        'validation_failed' => ['status' => 422, 'message' => 'Validation failed'],

        // 429 Too Many Requests
        'rate_limit_exceeded' => ['status' => 429, 'message' => 'Too many requests'],

        // 500 Internal Server Error
        'server_error' => ['status' => 500, 'message' => 'An unexpected error occurred'],
        'database_error' => ['status' => 500, 'message' => 'Database operation failed'],
        'payment_error' => ['status' => 500, 'message' => 'Payment processing failed'],
        'email_error' => ['status' => 500, 'message' => 'Failed to send email'],

        // 503 Service Unavailable
        'service_unavailable' => ['status' => 503, 'message' => 'Service temporarily unavailable'],
        'payment_not_configured' => ['status' => 503, 'message' => 'Payment system not configured'],
    ];

    /**
     * Create a success response
     *
     * @param mixed $data Response data
     * @param string|null $message Optional success message
     * @param int $status HTTP status code (default 200)
     * @return \WP_REST_Response
     */
    public static function success($data = null, ?string $message = null, int $status = 200): \WP_REST_Response {
        $response = [
            'success' => true,
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return new \WP_REST_Response($response, $status);
    }

    /**
     * Create a success response for paginated data
     *
     * @param array $items The items for current page
     * @param int $total Total number of items
     * @param int $page Current page number
     * @param int $per_page Items per page
     * @return \WP_REST_Response
     */
    public static function paginated(array $items, int $total, int $page, int $per_page): \WP_REST_Response {
        $total_pages = ceil($total / $per_page);

        return new \WP_REST_Response([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => $total_pages,
                'has_next' => $page < $total_pages,
                'has_prev' => $page > 1,
            ],
        ], 200);
    }

    /**
     * Create an error response using predefined error code
     *
     * @param string $code Error code from ERROR_CODES
     * @param string|null $custom_message Override default message
     * @param array $details Additional error details
     * @return \WP_REST_Response
     */
    public static function error(string $code, ?string $custom_message = null, array $details = []): \WP_REST_Response {
        $error_info = self::ERROR_CODES[$code] ?? self::ERROR_CODES['server_error'];

        $response = [
            'success' => false,
            'code' => $code,
            'message' => $custom_message ?? $error_info['message'],
        ];

        if (!empty($details)) {
            $response['details'] = $details;
        }

        return new \WP_REST_Response($response, $error_info['status']);
    }

    /**
     * Create a custom error response
     *
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param string|null $code Optional error code
     * @param array $details Additional error details
     * @return \WP_REST_Response
     */
    public static function custom_error(
        string $message,
        int $status = 400,
        ?string $code = null,
        array $details = []
    ): \WP_REST_Response {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($code !== null) {
            $response['code'] = $code;
        }

        if (!empty($details)) {
            $response['details'] = $details;
        }

        return new \WP_REST_Response($response, $status);
    }

    /**
     * Create a validation error response
     *
     * @param array $errors Field => error message pairs
     * @return \WP_REST_Response
     */
    public static function validation_error(array $errors): \WP_REST_Response {
        return new \WP_REST_Response([
            'success' => false,
            'code' => 'validation_failed',
            'message' => 'Validation failed',
            'errors' => $errors,
        ], 422);
    }

    /**
     * Create a created response (201)
     *
     * @param mixed $data Created resource data
     * @param string|null $message Optional message
     * @return \WP_REST_Response
     */
    public static function created($data = null, ?string $message = null): \WP_REST_Response {
        return self::success($data, $message ?? 'Resource created successfully', 201);
    }

    /**
     * Create a no content response (204)
     *
     * @return \WP_REST_Response
     */
    public static function no_content(): \WP_REST_Response {
        return new \WP_REST_Response(null, 204);
    }

    /**
     * Create an accepted response (202) for async operations
     *
     * @param string|null $message Status message
     * @param array $data Additional data
     * @return \WP_REST_Response
     */
    public static function accepted(?string $message = null, array $data = []): \WP_REST_Response {
        $response = [
            'success' => true,
            'message' => $message ?? 'Request accepted for processing',
        ];

        if (!empty($data)) {
            $response['data'] = $data;
        }

        return new \WP_REST_Response($response, 202);
    }

    /**
     * Get all defined error codes
     *
     * @return array
     */
    public static function get_error_codes(): array {
        return self::ERROR_CODES;
    }
}
