<?php
/**
 * Real-WordPress REST contract suite bootstrap (net 7).
 *
 * Boots a REAL WordPress (via the shared Peanut wp-harness) so Peanut Festival's
 * `register_rest_route('peanut-festival/v1', ...)` calls actually run and the
 * contract tests can pin real `/wp-json/peanut-festival/v1/*` responses. This is
 * intentionally SEPARATE from the existing mock/unit suites under tests/php — it
 * must never fall back to mocks.
 */

define('PLUGIN_MAIN_FILE', dirname(__DIR__, 2) . '/peanut-festival.php');

require __DIR__ . '/../../.peanut/wp-harness/bootstrap-wp.php';
