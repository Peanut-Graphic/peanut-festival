<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Festival refuses update packages that are not cryptographically ours.
 *
 * Before this gate, Festival had no updater and no signature check at all:
 * v1.3.1 shipped as a standalone unsigned ZIP with no manifest, and the live
 * update API did not recognise the slug. Whatever a site was handed, it
 * installed — transport trust standing in for authenticity.
 *
 * These tests pin the wiring rather than the crypto (the verifier itself is
 * covered in formflow-core). The wiring is what was missing, and it is the part
 * a future refactor can silently drop.
 */
final class SignedUpdateGateTest extends TestCase {

    private string $source;

    protected function setUp(): void {
        parent::setUp();
        $this->source = file_get_contents(dirname(__DIR__, 2) . '/peanut-festival.php');
    }

    public function test_the_gate_is_registered_on_plugins_loaded(): void {
        $this->assertStringContainsString(
            "add_action('plugins_loaded', 'peanut_festival_register_update_gate', 1)",
            $this->source,
            'The update gate is never registered, so nothing verifies an update package.'
        );
    }

    public function test_the_gate_is_constructed_with_this_plugins_identity(): void {
        $this->assertStringContainsString('PEANUT_FESTIVAL_BASENAME', $this->source);
        $this->assertStringContainsString('PEANUT_FESTIVAL_SIGNING_PUBKEY', $this->source);
        $this->assertStringContainsString("'peanut-festival'", $this->source);
    }

    public function test_it_pins_the_fleet_signing_key(): void {
        // The key the central publisher signs manifests against. A different key
        // here means every legitimate release is refused, and — worse — a key an
        // attacker chose would mean none of them are.
        $this->assertStringContainsString(
            "define('PEANUT_FESTIVAL_SIGNING_PUBKEY', 'NtHnWTBLVzCBKMAq9CO8LHDSD9ZfpGV0UloQdgToIwM=')",
            $this->source
        );
    }

    public function test_only_peanut_and_github_hosts_are_trusted(): void {
        $this->assertStringContainsString("['peanutgraphic.com', 'github.com']", $this->source);
    }

    public function test_a_missing_verifier_warns_loudly_instead_of_failing_open(): void {
        // If vendor/ is stripped from a package, the gate cannot load. The plugin
        // must say so rather than quietly install unverified updates.
        $this->assertStringContainsString('Updates are NOT being verified', $this->source);
        $this->assertStringContainsString('admin_notices', $this->source);
    }

    public function test_formflow_core_is_a_declared_dependency(): void {
        $composer = json_decode(file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);

        $this->assertArrayHasKey(
            'peanut/formflow-core',
            $composer['require'] ?? [],
            'Without formflow-core in require, vendor/ will not carry the verifier and the gate degrades to a notice.'
        );
    }
}
