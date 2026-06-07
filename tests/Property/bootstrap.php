<?php
/**
 * Bootstrap for property-based tests (net 6).
 *
 * Property tests exercise PURE PHP functions that need NO WordPress runtime.
 * We stub only the minimum the targeted classes touch (a constant + the
 * WP_REST_Response value object), then load the specific include files under
 * test. This deliberately avoids the full plugin/WP boot, keeping the property
 * suite fast, deterministic, and isolated from unrelated debt.
 *
 * @package Peanut_Festival\Tests\Property
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

// hash_ip/hash_ua/generate_fingerprint salt their digests with NONCE_SALT.
if ( ! defined( 'NONCE_SALT' ) ) {
    define( 'NONCE_SALT', 'property-test-fixed-nonce-salt-42' );
}

// Minimal WP_REST_Response value object (paginated() returns one).
if ( ! class_exists( 'WP_REST_Response' ) ) {
    class WP_REST_Response {
        /** @var mixed */
        public $data;
        /** @var int */
        public $status;

        public function __construct( $data = null, $status = 200 ) {
            $this->data   = $data;
            $this->status = $status;
        }

        public function get_data() {
            return $this->data;
        }

        public function get_status() {
            return $this->status;
        }
    }
}

// Load the include files containing the pure functions under test.
$includes = dirname( __DIR__, 2 ) . '/includes';
require_once $includes . '/class-rest-response.php';
require_once $includes . '/class-voting.php';
