<?php

declare(strict_types=1);

/**
 * Test-doubles pre-flight: which doubling route is ready in this project?
 *
 * Run in Step 2 of testo-test-doubles. For each library (Double, Mockery) it reports whether the
 * library, its Testo bridge and the bridge plugin registration in `testo.php` are present, and prints the
 * exact fix for anything missing. The hand-written route needs no tooling and is always available.
 *
 * Usage:
 *   php precheck.php [--root=PATH]
 *
 * --root=PATH   Project root to inspect (default: current working directory).
 *
 * Reads only the filesystem (composer.json, vendor/, testo.php). Writes nothing.
 * Exit codes: 0 at least one library route READY, 1 hand-written only, 2 no composer.json.
 */

$root = null;
foreach (\array_slice($argv, 1) as $arg) {
    if (\preg_match('/^--root=(.+)$/', $arg, $m)) {
        $root = $m[1];
        continue;
    }
    \fwrite(\STDERR, "unknown argument: {$arg}\n");
}

$root = \rtrim($root ?? \getcwd(), "/\\");
$path = static fn(string ...$p): string => $root . '/' . \implode('/', $p);
$exists = static fn(string ...$p): bool => \file_exists($root . '/' . \implode('/', $p));

if (!$exists('composer.json')) {
    \fwrite(\STDERR, "No composer.json under {$root} — not a Composer project. Pass --root=PATH.\n");
    exit(2);
}

// --- Packages ------------------------------------------------------------------------------------

$composer = \json_decode((string) \file_get_contents($path('composer.json')), true) ?: [];
$declared = \array_merge($composer['require'] ?? [], $composer['require-dev'] ?? []);

$testoInstalled = $exists('vendor', 'testo', 'testo') || $exists('vendor', 'bin', 'testo') || $exists('vendor', 'bin', 'testo.bat');

// Plugin registration is read from the config source, not resolved at runtime: loading testo.php would
// execute project code. Any of the usual config file names counts.
$configSource = '';
foreach (['testo.php', 'testo.php.dist', 'testo.dist.php'] as $candidate) {
    if ($exists($candidate)) {
        $configSource .= (string) \file_get_contents($path($candidate));
    }
}
$configFound = $configSource !== '';

$libraries = [
    'DOUBLE' => [
        'library' => 'jasonmccreary/double',
        'bridge' => 'testo/bridge-double',
        'plugin' => 'DoublePlugin',
        'reference' => 'references/double.md',
        'phpMin' => 80300,
    ],
    'MOCKERY' => [
        'library' => 'mockery/mockery',
        'bridge' => 'testo/bridge-mockery',
        'plugin' => 'MockeryPlugin',
        'reference' => 'references/mockery.md',
        'phpMin' => 80200,
    ],
];

$yn = static fn(bool $b): string => $b ? 'yes' : 'NO';

echo "# Test doubles pre-flight\n\n";
echo "Project root: `{$root}`\n";
echo 'PHP (this binary): ' . \PHP_VERSION . "\n";
echo 'testo/testo installed: ' . $yn($testoInstalled) . "\n";
echo 'testo.php found: ' . $yn($configFound) . "\n\n";

$anyReady = false;

foreach ($libraries as $label => $lib) {
    [$vendor, $package] = \explode('/', $lib['library']);
    [$bridgeVendor, $bridgePackage] = \explode('/', $lib['bridge']);

    $libraryInstalled = $exists('vendor', $vendor, $package);
    $bridgeInstalled = $exists('vendor', $bridgeVendor, $bridgePackage);
    $pluginRegistered = $configFound && \str_contains($configSource, $lib['plugin']);
    $phpOk = \PHP_VERSION_ID >= $lib['phpMin'];

    echo "## {$label}\n\n";
    echo "| Component | Present | Source |\n|---|:---:|---|\n";
    echo "| {$lib['library']} | {$yn($libraryInstalled)} | " . ($declared[$lib['library']] ?? '—') . " |\n";
    echo "| {$lib['bridge']} | {$yn($bridgeInstalled)} | " . ($declared[$lib['bridge']] ?? '—') . " |\n";
    echo "| {$lib['plugin']} registered | {$yn($pluginRegistered)} | " . ($configFound ? 'testo.php `plugins:`' : 'no testo.php') . " |\n";
    echo '| PHP >= ' . \sprintf('%d.%d', \intdiv($lib['phpMin'], 10000), \intdiv($lib['phpMin'] % 10000, 100)) . " | {$yn($phpOk)} | this binary |\n\n";

    $ready = $libraryInstalled && $bridgeInstalled && $pluginRegistered && $phpOk;
    $anyReady = $anyReady || $ready;

    if ($ready) {
        echo "**{$label}: READY.** Follow `{$lib['reference']}`.\n\n";
        continue;
    }

    if (!$libraryInstalled && !$bridgeInstalled) {
        $verdict = $phpOk ? 'NOT INSTALLED' : 'UNAVAILABLE (PHP too old)';
        echo "**{$label}: {$verdict}.**";
        $phpOk and print " Install: `composer require --dev {$lib['bridge']}` then register `{$lib['plugin']}` (see `{$lib['reference']}` §2).";
        echo "\n\n";
        continue;
    }

    echo "**{$label}: INSTALLED BUT NOT WIRED.** Expectations will go unverified until fixed:\n\n";
    $bridgeInstalled or print "- `composer require --dev {$lib['bridge']}`\n";
    ($bridgeInstalled && !$libraryInstalled) and print "- Bridge present but `{$lib['library']}` missing from vendor/ — run `composer install`.\n";
    $pluginRegistered or print "- Register `{$lib['plugin']}` in `testo.php` `plugins:` (see `{$lib['reference']}` §2.2).\n";
    $phpOk or print "- {$lib['library']} needs PHP >= " . \sprintf('%d.%d', \intdiv($lib['phpMin'], 10000), \intdiv($lib['phpMin'] % 10000, 100)) . "; this binary is " . \PHP_VERSION . ".\n";
    echo "\n";
}

echo "## HAND-WRITTEN\n\n**HAND-WRITTEN: READY.** Always available — `references/handwritten.md`.\n\n";

if ($anyReady) {
    echo "Use the library route the surrounding tests already use; keep the project on one library.\n";
    exit(0);
}

echo "No library route is ready. Default to hand-written fakes; offer an install only when the user asks for a mocking library.\n";
exit(1);
