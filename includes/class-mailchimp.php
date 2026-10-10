<?php
/**
 * Mailchimp integration class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Peanut_Festival_Mailchimp {

    private static ?Peanut_Festival_Mailchimp $instance = null;
    private string $api_key = '';
    private string $server_prefix = '';
    private string $list_id = '';

    public static function get_instance(): Peanut_Festival_Mailchimp {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->api_key = Peanut_Festival_Settings::get('mailchimp_api_key', '');
        $this->list_id = Peanut_Festival_Settings::get('mailchimp_list_id', '');

        // Extract server prefix from API key (e.g., us1, us2, etc.)
        if ($this->api_key && strpos($this->api_key, '-') !== false) {
            $parts = explode('-', $this->api_key);
            $this->server_prefix = end($parts);
        }
    }

    /**
     * Make API request to Mailchimp
     */
    private function request(string $endpoint, string $method = 'GET', array $data = []): array {
        if (empty($this->api_key) || empty($this->server_prefix)) {
            return ['success' => false, 'error' => 'Mailchimp API key not configured'];
        }

        $url = "https://{$this->server_prefix}.api.mailchimp.com/3.0/{$endpoint}";

        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode('anystring:' . $this->api_key),
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ];

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = wp_json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 200 && $code < 300) {
            return ['success' => true, 'data' => $body];
        }

        return [
            'success' => false,
            'error' => $body['detail'] ?? $body['title'] ?? 'API request failed',
            'status' => $code,
        ];
    }

    /**
     * Test API connection
     */
    public static function test_connection(): array {
        $instance = self::get_instance();
        $result = $instance->request('ping');

        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Connected to Mailchimp successfully',
                'health_status' => $result['data']['health_status'] ?? 'ok',
            ];
        }

        return $result;
    }

    /**
     * Get account info
     */
    public static function get_account_info(): array {
        $instance = self::get_instance();
        return $instance->request('');
    }

    /**
     * Get all lists/audiences
     */
    public static function get_lists(): array {
        $instance = self::get_instance();
        $result = $instance->request('lists?count=100');

        if ($result['success'] && isset($result['data']['lists'])) {
            return [
                'success' => true,
                'data' => array_map(function($list) {
                    return [
                        'id' => $list['id'],
                        'name' => $list['name'],
                        'member_count' => $list['stats']['member_count'] ?? 0,
                    ];
                }, $result['data']['lists']),
            ];
        }

        return $result;
    }

    /**
     * Get list details
     */
    public static function get_list_info(string $list_id = ''): array {
        $instance = self::get_instance();
        $list_id = $list_id ?: $instance->list_id;

        if (empty($list_id)) {
            return ['success' => false, 'error' => 'No list ID specified'];
        }

        return $instance->request("lists/{$list_id}");
    }

    /**
     * Subscribe a single email
     */
    public static function subscribe(string $email, array $merge_fields = [], array $tags = []): array {
        $instance = self::get_instance();

        if (empty($instance->list_id)) {
            return ['success' => false, 'error' => 'Mailchimp list ID not configured'];
        }

        $subscriber_hash = md5(strtolower($email));

        $data = [
            'email_address' => $email,
            'status' => 'subscribed',
        ];

        if (!empty($merge_fields)) {
            $data['merge_fields'] = $merge_fields;
        }

        if (!empty($tags)) {
            $data['tags'] = $tags;
        }

        // Use PUT to update or create (upsert)
        $result = $instance->request(
            "lists/{$instance->list_id}/members/{$subscriber_hash}",
            'PUT',
            $data
        );

        return $result;
    }

    /**
     * Subscribe multiple emails in batch
     */
    public static function batch_subscribe(array $subscribers): array {
        $instance = self::get_instance();

        if (empty($instance->list_id)) {
            return ['success' => false, 'error' => 'Mailchimp list ID not configured'];
        }

        $members = array_map(function($sub) {
            $member = [
                'email_address' => $sub['email'],
                'status' => 'subscribed',
            ];

            if (!empty($sub['merge_fields'])) {
                $member['merge_fields'] = $sub['merge_fields'];
            }

            if (!empty($sub['tags'])) {
                $member['tags'] = $sub['tags'];
            }

            return $member;
        }, $subscribers);

        $result = $instance->request(
            "lists/{$instance->list_id}",
            'POST',
            [
                'members' => $members,
                'update_existing' => true,
            ]
        );

        if ($result['success']) {
            return [
                'success' => true,
                'new_members' => $result['data']['new_members'] ?? 0,
                'updated_members' => $result['data']['updated_members'] ?? 0,
                'error_count' => $result['data']['error_count'] ?? 0,
                'errors' => $result['data']['errors'] ?? [],
            ];
        }

        return $result;
    }

    /**
     * Sync performers to Mailchimp
     */
    public static function sync_performers(?int $festival_id = null): array {
        $performers = Peanut_Festival_Performers::get_all([
            'festival_id' => $festival_id,
            'application_status' => 'accepted',
        ]);

        $subscribers = [];
        foreach ($performers as $performer) {
            if (empty($performer->email)) continue;

            $subscribers[] = [
                'email' => $performer->email,
                'merge_fields' => [
                    'FNAME' => explode(' ', $performer->name)[0] ?? '',
                    'LNAME' => implode(' ', array_slice(explode(' ', $performer->name), 1)) ?: '',
                ],
                'tags' => ['Performer', 'Festival ' . date('Y')],
            ];
        }

        if (empty($subscribers)) {
            return ['success' => true, 'message' => 'No performers to sync', 'synced' => 0];
        }

        $result = self::batch_subscribe($subscribers);

        if ($result['success']) {
            $result['message'] = sprintf(
                'Synced %d new, %d updated performers',
                $result['new_members'] ?? 0,
                $result['updated_members'] ?? 0
            );
            $result['synced'] = ($result['new_members'] ?? 0) + ($result['updated_members'] ?? 0);
        }

        return $result;
    }

    /**
     * Sync volunteers to Mailchimp
     */
    public static function sync_volunteers(?int $festival_id = null): array {
        $volunteers = Peanut_Festival_Volunteers::get_all([
            'festival_id' => $festival_id,
            'status' => 'active',
        ]);

        $subscribers = [];
        foreach ($volunteers as $volunteer) {
            if (empty($volunteer->email)) continue;

            $subscribers[] = [
                'email' => $volunteer->email,
                'merge_fields' => [
                    'FNAME' => explode(' ', $volunteer->name)[0] ?? '',
                    'LNAME' => implode(' ', array_slice(explode(' ', $volunteer->name), 1)) ?: '',
                ],
                'tags' => ['Volunteer', 'Festival ' . date('Y')],
            ];
        }

        if (empty($subscribers)) {
            return ['success' => true, 'message' => 'No volunteers to sync', 'synced' => 0];
        }

        $result = self::batch_subscribe($subscribers);

        if ($result['success']) {
            $result['message'] = sprintf(
                'Synced %d new, %d updated volunteers',
                $result['new_members'] ?? 0,
                $result['updated_members'] ?? 0
            );
            $result['synced'] = ($result['new_members'] ?? 0) + ($result['updated_members'] ?? 0);
        }

        return $result;
    }

    /**
     * Sync attendees to Mailchimp
     */
    public static function sync_attendees(?int $festival_id = null): array {
        $attendees = Peanut_Festival_Attendees::get_all([
            'festival_id' => $festival_id,
        ]);

        $subscribers = [];
        foreach ($attendees as $attendee) {
            if (empty($attendee->email)) continue;

            $subscribers[] = [
                'email' => $attendee->email,
                'merge_fields' => [
                    'FNAME' => explode(' ', $attendee->name)[0] ?? '',
                    'LNAME' => implode(' ', array_slice(explode(' ', $attendee->name), 1)) ?: '',
                ],
                'tags' => ['Attendee', 'Festival ' . date('Y')],
            ];
        }

        if (empty($subscribers)) {
            return ['success' => true, 'message' => 'No attendees to sync', 'synced' => 0];
        }

        $result = self::batch_subscribe($subscribers);

        if ($result['success']) {
            $result['message'] = sprintf(
                'Synced %d new, %d updated attendees',
                $result['new_members'] ?? 0,
                $result['updated_members'] ?? 0
            );
            $result['synced'] = ($result['new_members'] ?? 0) + ($result['updated_members'] ?? 0);
        }

        return $result;
    }

    /**
     * Sync all contacts to Mailchimp
     */
    public static function sync_all(?int $festival_id = null): array {
        $results = [
            'performers' => self::sync_performers($festival_id),
            'volunteers' => self::sync_volunteers($festival_id),
            'attendees' => self::sync_attendees($festival_id),
        ];

        $total_synced = 0;
        $errors = [];

        foreach ($results as $type => $result) {
            if ($result['success']) {
                $total_synced += $result['synced'] ?? 0;
            } else {
                $errors[] = "{$type}: " . ($result['error'] ?? 'Unknown error');
            }
        }

        return [
            'success' => empty($errors),
            'total_synced' => $total_synced,
            'results' => $results,
            'errors' => $errors,
        ];
    }

    /**
     * Add tags to a subscriber
     */
    public static function add_tags(string $email, array $tags): array {
        $instance = self::get_instance();

        if (empty($instance->list_id)) {
            return ['success' => false, 'error' => 'Mailchimp list ID not configured'];
        }

        $subscriber_hash = md5(strtolower($email));

        $tag_data = array_map(function($tag) {
            return ['name' => $tag, 'status' => 'active'];
        }, $tags);

        return $instance->request(
            "lists/{$instance->list_id}/members/{$subscriber_hash}/tags",
            'POST',
            ['tags' => $tag_data]
        );
    }

    /**
     * Get subscriber info
     */
    public static function get_subscriber(string $email): array {
        $instance = self::get_instance();

        if (empty($instance->list_id)) {
            return ['success' => false, 'error' => 'Mailchimp list ID not configured'];
        }

        $subscriber_hash = md5(strtolower($email));

        return $instance->request("lists/{$instance->list_id}/members/{$subscriber_hash}");
    }
}
