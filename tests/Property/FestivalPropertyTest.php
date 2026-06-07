<?php
/**
 * Property-based tests (net 6) for pure Peanut Festival logic.
 *
 * Each test asserts an INVARIANT that must hold across a wide, seeded space of
 * inputs — not a single hand-picked example. Seeds are fixed so the suite is
 * deterministic (per the testing-standard determinism rule). A failing property
 * indicates a real bug in the production code, never a reason to weaken the
 * assertion.
 *
 * Targets (all pure — no WordPress runtime, no $wpdb):
 *   - Peanut_Festival_REST_Response::paginated   (pagination math)
 *   - Peanut_Festival_Voting::hash_ip / hash_ua  (deterministic salted digest)
 *   - Peanut_Festival_Voting::generate_fingerprint (canonical device hash)
 *
 * @package Peanut_Festival\Tests\Property
 */

declare( strict_types=1 );

namespace Peanut_Festival\Tests\Property;

use PHPUnit\Framework\TestCase;
use Peanut_Festival_REST_Response;
use Peanut_Festival_Voting;

final class FestivalPropertyTest extends TestCase {

    private const RUNS = 500;

    public static function setUpBeforeClass(): void {
        // Make the suite runnable in isolation regardless of which config invoked
        // it (the dedicated Property bootstrap also defines these).
        if ( ! defined( 'ABSPATH' ) ) {
            define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
        }
        if ( ! defined( 'NONCE_SALT' ) ) {
            define( 'NONCE_SALT', 'property-test-fixed-nonce-salt-42' );
        }
        if ( ! class_exists( 'WP_REST_Response' ) ) {
            require dirname( __DIR__ ) . '/Property/bootstrap.php';
        }
        $includes = dirname( __DIR__, 2 ) . '/includes';
        foreach ( array( 'class-rest-response', 'class-voting' ) as $f ) {
            if ( is_file( "$includes/$f.php" ) ) {
                require_once "$includes/$f.php";
            }
        }
    }

    protected function setUp(): void {
        mt_srand( 909090 ); // Fixed seed → reproducible input sequence.
    }

    private function randInt( int $min, int $max ): int {
        return mt_rand( $min, $max );
    }

    private function randString( int $len ): string {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEF0123456789.:-_ ';
        $out      = '';
        for ( $i = 0; $i < $len; $i++ ) {
            $out .= $alphabet[ mt_rand( 0, strlen( $alphabet ) - 1 ) ];
        }
        return $out;
    }

    // ---------------------------------------------------------------------
    // REST_Response::paginated — pagination metadata math.
    //
    // Invariants (over positive total/page/per_page):
    //  (a) total_pages == ceil(total / per_page); it is always >= 0 and, when
    //      total > 0, >= 1.
    //  (b) has_prev is true iff page > 1.
    //  (c) has_next is true iff page < total_pages — and on the LAST real page
    //      (page == total_pages, total > 0) has_next is false (no off-by-one
    //      that would advertise a non-existent next page).
    //  (d) The echoed total/page/per_page round-trip unchanged.
    // ---------------------------------------------------------------------

    public function test_paginated_math_invariants(): void {
        for ( $i = 0; $i < self::RUNS; $i++ ) {
            $total    = $this->randInt( 0, 100000 );
            $perPage  = $this->randInt( 1, 200 );
            $maxPage  = (int) max( 1, ceil( $total / $perPage ) );
            $page     = $this->randInt( 1, $maxPage + 2 ); // include past-the-end pages

            $resp = Peanut_Festival_REST_Response::paginated( array(), $total, $page, $perPage );
            $p    = $resp->get_data()['pagination'];

            $expectedPages = (int) ceil( $total / $perPage );

            $this->assertSame( $expectedPages, (int) $p['total_pages'], 'total_pages must equal ceil(total/per_page)' );
            $this->assertGreaterThanOrEqual( 0, $p['total_pages'] );

            // (b) has_prev.
            $this->assertSame( $page > 1, (bool) $p['has_prev'], "has_prev wrong at page=$page" );

            // (c) has_next === page < total_pages.
            $this->assertSame(
                $page < $expectedPages,
                (bool) $p['has_next'],
                sprintf( 'has_next wrong: page=%d total_pages=%d', $page, $expectedPages )
            );

            // On the last real page, never advertise a next page.
            if ( $total > 0 && $page === $expectedPages ) {
                $this->assertFalse( (bool) $p['has_next'], 'Last page must not advertise has_next' );
            }

            // (d) round-trip echo.
            $this->assertSame( $total, $p['total'] );
            $this->assertSame( $page, $p['page'] );
            $this->assertSame( $perPage, $p['per_page'] );

            // 200 status always.
            $this->assertSame( 200, $resp->get_status() );
        }
    }

    // ---------------------------------------------------------------------
    // Voting::hash_ip / hash_ua — deterministic salted SHA-256 digest.
    //
    // Invariants:
    //  (a) Determinism: same input -> same hash, always.
    //  (b) Shape: output is exactly 64 lowercase hex chars (sha256).
    //  (c) Sensitivity: distinct inputs almost always yield distinct hashes
    //      (no truncation/collapse). We assert injectivity over the sampled set.
    //  (d) hash_ip and hash_ua use the same salt+algo, so for the SAME string
    //      they agree — but distinct namespacing is NOT claimed by the code, so
    //      we only assert each is independently well-formed + deterministic.
    // ---------------------------------------------------------------------

    public function test_hash_ip_is_deterministic_and_well_formed(): void {
        $seen = array();
        for ( $i = 0; $i < self::RUNS; $i++ ) {
            $ip = $this->randString( $this->randInt( 0, 40 ) );

            $h1 = Peanut_Festival_Voting::hash_ip( $ip );
            $h2 = Peanut_Festival_Voting::hash_ip( $ip );

            $this->assertSame( $h1, $h2, 'hash_ip must be deterministic' );
            $this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $h1, "Malformed digest for input '$ip'" );

            // Injectivity over distinct sampled inputs.
            if ( isset( $seen[ $ip ] ) ) {
                $this->assertSame( $seen[ $ip ], $h1, 'Same input must map to same hash' );
            } else {
                $this->assertNotContains( $h1, $seen, "Collision: distinct input '$ip' produced an existing digest" );
                $seen[ $ip ] = $h1;
            }
        }
    }

    public function test_hash_ua_is_deterministic_and_well_formed(): void {
        for ( $i = 0; $i < self::RUNS; $i++ ) {
            $ua = $this->randString( $this->randInt( 0, 80 ) );
            $h  = Peanut_Festival_Voting::hash_ua( $ua );
            $this->assertSame( $h, Peanut_Festival_Voting::hash_ua( $ua ), 'hash_ua must be deterministic' );
            $this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $h );
        }
    }

    // ---------------------------------------------------------------------
    // Voting::generate_fingerprint — canonical device-fingerprint hash.
    //
    // The function reads ONLY five keys (user_agent, screen_resolution,
    // timezone, language, platform). Invariants:
    //  (a) Determinism + shape (64 hex).
    //  (b) Canonicalization: adding UNRELATED extra keys to the input array must
    //      NOT change the hash (only the five tracked attributes matter).
    //  (c) Key-order independence: the same five values in a different array
    //      order yield the same hash.
    //  (d) Sensitivity: changing any one tracked attribute changes the hash.
    // ---------------------------------------------------------------------

    public function test_fingerprint_canonical_and_sensitive(): void {
        for ( $i = 0; $i < self::RUNS; $i++ ) {
            $base = array(
                'user_agent'        => $this->randString( 20 ),
                'screen_resolution' => $this->randInt( 320, 3840 ) . 'x' . $this->randInt( 240, 2160 ),
                'timezone'          => 'UTC' . $this->randInt( -12, 14 ),
                'language'          => $this->randString( 5 ),
                'platform'          => $this->randString( 8 ),
            );

            $h = Peanut_Festival_Voting::generate_fingerprint( $base );

            // (a) determinism + shape.
            $this->assertSame( $h, Peanut_Festival_Voting::generate_fingerprint( $base ), 'fingerprint must be deterministic' );
            $this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $h );

            // (b) extra unrelated keys must not change the hash.
            $withNoise              = $base;
            $withNoise['ignored_x'] = $this->randString( 10 );
            $withNoise['cookies']   = array( 'a', 'b' );
            $this->assertSame(
                $h,
                Peanut_Festival_Voting::generate_fingerprint( $withNoise ),
                'Untracked keys must not affect the fingerprint'
            );

            // (c) key-order independence.
            $reordered = array(
                'platform'          => $base['platform'],
                'language'          => $base['language'],
                'timezone'          => $base['timezone'],
                'screen_resolution' => $base['screen_resolution'],
                'user_agent'        => $base['user_agent'],
            );
            $this->assertSame(
                $h,
                Peanut_Festival_Voting::generate_fingerprint( $reordered ),
                'Fingerprint must be independent of input key order'
            );

            // (d) sensitivity: flip one tracked attribute.
            $changed             = $base;
            $changed['platform'] = $base['platform'] . '-X';
            $this->assertNotSame(
                $h,
                Peanut_Festival_Voting::generate_fingerprint( $changed ),
                'Changing a tracked attribute must change the fingerprint'
            );
        }
    }
}
