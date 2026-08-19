<?php

declare(strict_types=1);

/** Enforce Peanut Festival's PHP floors and installable frontend toolchain. */

$root = dirname(__DIR__);
$failures = [];

$read = static function (string $relative) use ($root, &$failures): string {
    $contents = @file_get_contents($root . '/' . $relative);
    if ($contents === false) {
        $failures[] = sprintf('%s is missing or unreadable', $relative);
        return '';
    }
    return $contents;
};

$composer = json_decode($read('composer.json'), true);
$lock = json_decode($read('composer.lock'), true);
$frontend = json_decode($read('frontend/package.json'), true);
$frontendLock = json_decode($read('frontend/package-lock.json'), true);

if (!is_array($composer)
    || ($composer['require']['php'] ?? null) !== '>=8.0'
    || ($composer['config']['platform']['php'] ?? null) !== '8.1.0') {
    $failures[] = 'Composer must preserve PHP 8.0 hosts and exact PHP 8.1 development resolution';
}

if (!is_array($lock)
    || ($lock['platform']['php'] ?? null) !== '>=8.0'
    || ($lock['platform-overrides']['php'] ?? null) !== '8.1.0') {
    $failures[] = 'composer.lock must preserve PHP 8.0 hosts and exact PHP 8.1 development resolution';
}

if (is_array($lock)) {
    $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);
    $versions = [];
    foreach ($packages as $package) {
        if (isset($package['name'], $package['version'])) {
            $versions[$package['name']] = $package['version'];
        }
    }
    if (($versions['doctrine/instantiator'] ?? null) !== '2.0.0') {
        $failures[] = 'composer.lock must retain the PHP 8.1 development-floor witness';
    }
}

if (!preg_match('/^\s*\* Requires PHP:\s*8\.0\s*$/m', $read('peanut-festival.php'))) {
    $failures[] = 'peanut-festival.php must declare the PHP 8.0 host floor';
}

$readme = $read('README.md');
if (!preg_match('/^- PHP 8\.0\+$/m', $readme)
    || !preg_match('/^- PHP 8\.1\+ for Composer dependencies and test tooling$/m', $readme)) {
    $failures[] = 'README.md must distinguish PHP 8.0 hosts from PHP 8.1 development';
}

$ci = $read('.github/workflows/ci.yml');
foreach ([
    '/php-version:\s*[\'\"]8\.0[\'\"]/' => 'exact PHP 8.0 parser job',
    '/verify-runtime-contract\.php --expect-runtime=8\.0/' => 'PHP 8.0 runtime assertion',
    '/php-version:\s*[\'\"]8\.1[\'\"]/' => 'exact PHP 8.1 development job',
    '/verify-runtime-contract\.php --expect-development-runtime=8\.1/' => 'PHP 8.1 runtime assertion',
] as $pattern => $description) {
    if (!preg_match($pattern, $ci)) {
        $failures[] = sprintf('%s is missing from CI', $description);
    }
}

if (!is_array($frontend)
    || ($frontend['devDependencies']['vite'] ?? null) !== '^8.0.16'
    || ($frontend['devDependencies']['@vitejs/plugin-react'] ?? null) !== '^6.0.5') {
    $failures[] = 'frontend package declarations must retain Vite 8 with compatible React plugin 6';
}

$lockedPlugin = $frontendLock['packages']['node_modules/@vitejs/plugin-react']['version'] ?? null;
$lockedVite = $frontendLock['packages']['node_modules/vite']['version'] ?? null;
if ($lockedPlugin !== '6.0.5' || $lockedVite !== '8.2.1') {
    $failures[] = 'frontend lock must contain React plugin 6.0.5 with Vite 8.0.3';
}

$argument = $argv[1] ?? '';
if ($argument === '--expect-runtime=8.0' && PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION !== '8.0') {
    $failures[] = sprintf('expected PHP 8.0 host runtime, got %s', PHP_VERSION);
}
if ($argument === '--expect-development-runtime=8.1' && PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION !== '8.1') {
    $failures[] = sprintf('expected PHP 8.1 development runtime, got %s', PHP_VERSION);
}

if ($failures !== []) {
    fwrite(STDERR, "Runtime contract failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "Runtime contract passed (PHP host 8.0, development 8.1; Vite 8/plugin-react 6).\n");
