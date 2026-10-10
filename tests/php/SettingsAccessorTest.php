<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Guards against calls to Peanut_Festival_Settings methods that do not exist.
 *
 * The Stripe webhook handler and the Mailchimp integration called
 * Peanut_Festival_Settings::get_option(), which was never defined, so both
 * paths ended in a fatal "Call to undefined method" error.
 */

use PHPUnit\Framework\TestCase;

class SettingsAccessorTest extends TestCase
{
    public function test_every_static_settings_call_targets_an_existing_method(): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PEANUT_FESTIVAL_PATH . 'includes'));
        $missing = [];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            if (!preg_match_all('/Peanut_Festival_Settings::([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $source, $matches)) {
                continue;
            }
            foreach (array_unique($matches[1]) as $method) {
                if (!method_exists(Peanut_Festival_Settings::class, $method)) {
                    $missing[] = basename($file->getPathname()) . ': ' . $method . '()';
                }
            }
        }

        $this->assertSame([], $missing, 'Calls to undefined Peanut_Festival_Settings methods.');
    }

    public function test_mailchimp_reads_its_configured_credentials(): void
    {
        global $mock_options;
        $mock_options['peanut_festival_settings'] = [
            'mailchimp_api_key' => 'abc123-us21',
            'mailchimp_list_id' => 'list-9',
        ];

        $reflection = new ReflectionClass(Peanut_Festival_Mailchimp::class);
        $reflection->getProperty('instance')->setValue(null, null);

        $mailchimp = Peanut_Festival_Mailchimp::get_instance();

        $this->assertSame('abc123-us21', $reflection->getProperty('api_key')->getValue($mailchimp));
        $this->assertSame('list-9', $reflection->getProperty('list_id')->getValue($mailchimp));

        $reflection->getProperty('instance')->setValue(null, null);
        $mock_options['peanut_festival_settings'] = [];
    }
}
