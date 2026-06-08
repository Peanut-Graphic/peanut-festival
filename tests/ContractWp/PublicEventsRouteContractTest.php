<?php
/**
 * Real-WordPress REST contract test (net 7) for the public events catalog route.
 *
 * Pins the REAL `GET /peanut-festival/v1/events` route registered by
 * \Peanut_Festival_REST_API::register_routes(). The route is
 * `permission_callback => '__return_true'` (public by design — read-only,
 * non-PII events catalog the frontend pulls before sign-in), so it is the
 * stable, gettable surface to lock down.
 *
 * Documented response shape (see Peanut_Festival_REST_API::get_public_events):
 *   200 => [ 'success' => true, 'data' => array<...> ]
 *
 * This boots a real WordPress and dispatches through the real REST server —
 * NO mocks. If the route or shape regresses, this fails.
 */

namespace Peanut_Festival\Tests\ContractWp;

use WP_UnitTestCase;
use WP_REST_Request;

class PublicEventsRouteContractTest extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();

        // The events callback reads from the plugin's real custom tables via
        // Peanut_Festival_Shows::get_all(). Create the real schema (and roles/
        // options/pages) the way activation does, so the query runs against
        // real tables rather than emitting a missing-table DB warning.
        \Peanut_Festival_Activator::activate();

        // The plugin registers its public + admin routes on rest_api_init when
        // the main file is loaded. Rebuild the REST server so those routes are
        // live for this test.
        global $wp_rest_server;
        $wp_rest_server = null;
        do_action('rest_api_init');
    }

    public function test_events_route_is_registered(): void {
        $routes = rest_get_server()->get_routes();
        $this->assertArrayHasKey(
            '/peanut-festival/v1/events',
            $routes,
            'Public events catalog route must be registered on a real WordPress.'
        );
    }

    public function test_get_events_returns_documented_contract(): void {
        $request  = new WP_REST_Request('GET', '/peanut-festival/v1/events');
        $response = rest_get_server()->dispatch($request);

        // Real status from the real callback.
        $this->assertSame(
            200,
            $response->get_status(),
            'Public events catalog must return HTTP 200.'
        );

        $data = $response->get_data();

        // Documented response-shape keys.
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['data']);
    }
}
