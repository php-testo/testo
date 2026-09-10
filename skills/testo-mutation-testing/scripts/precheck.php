<?php

declare(strict_types=1);

/**
 * Mutation-testing pre-flight: is the Infection + Testo toolchain in place?
 *
 * Run this in Phase 0 of testo-mutation-testing. It checks the pieces every later phase
 * relies on — Infection itself, the `testo/bridge-infection` adapter (and that Infection's
 * extension-installer actually registered it), an `infection.json` pointed at Testo, and a
 * coverage driver — and prints the exact fix for anything missing.
 *
 * Usage:
 *   php precheck.php [--root=PATH]
 *
 * --root=PATH   Project root to inspect (default: current working directory).
 *
 * Reads only the filesystem (composer.json, vendor/, infection.json). Writes nothing.
 * Exit codes: 0 READY, 1 NOT READY, 2 no composer.json / not a PHP project.
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

// A binary may ship as `bin` (POSIX) or `bin.bat` (Windows shim) — accept any.
$hasBin = static fn(string $name): bool => $exists('vendor', 'bin', $name)
    || $exists('vendor', 'bin', $name . '.bat');

// Vendor roots to inspect: the project's own, plus bamarni/composer-bin-plugin namespaces
// (`<target-directory>/*/vendor`), where tool-isolating projects keep Infection.
$binTarget = $composer['extra']['bamarni-bin']['target-directory'] ?? 'vendor-bin';
$vendors = \array_merge(
    [$path('vendor')],
    \glob($path($binTarget, '*', 'vendor'), \GLOB_ONLYDIR) ?: [],
);
$inVendor = static function (string ...$p) use ($vendors): bool {
    foreach ($vendors as $vendor) {
        if (\file_exists($vendor . '/' . \implode('/', $p))) {
            return true;
        }
    }
    return false;
};

$testoInstalled = $exists('vendor', 'testo', 'testo') || $hasBin('testo');
$infectionInstalled = $hasBin('infection') || $inVendor('infection', 'infection');
$bridgeInstalled = $inVendor('testo', 'bridge-infection');

// Infection discovers adapters through infection/extension-installer, which generates this file
// at install time. The bridge is usable only if it made it into that list.
$bridgeRegistered = false;
foreach ($vendors as $vendor) {
    $generated = $vendor . '/infection/extension-installer/src/GeneratedExtensionsConfig.php';
    if (\is_file($generated) && \str_contains((string) \file_get_contents($generated), 'TestoAdapterFactory')) {
        $bridgeRegistered = true;
        break;
    }
}

$allowed = static fn(array $c): bool => ($c['config']['allow-plugins']['infection/extension-installer'] ?? false) === true;
$pluginAllowed = $allowed($composer);
foreach (\glob($path($binTarget, '*', 'composer.json')) ?: [] as $nsComposer) {
    $pluginAllowed = $pluginAllowed || $allowed(\json_decode((string) \file_get_contents($nsComposer), true) ?: []);
}

// --- infection.json ------------------------------------------------------------------------------

$configFile = null;
foreach (['infection.json', 'infection.json5', 'infection.json.dist'] as $candidate) {
    if ($exists($candidate)) {
        $configFile = $candidate;
        break;
    }
}

$config = $configFile !== null
    ? (\json_decode((string) \file_get_contents($path($configFile)), true) ?: [])
    : [];
$testFramework = $config['testFramework'] ?? null;
$sourceDirs = $config['source']['directories'] ?? [];
$tmpDir = $config['tmpDir'] ?? null;
// Phase 4 passes `--test-framework=testo` on the CLI, so the config key is a convenience, not a gate.
$configOk = $configFile !== null && $sourceDirs !== [];
$frameworkPinned = $testFramework === 'testo';

// --- Coverage driver -----------------------------------------------------------------------------

// Checked in *this* PHP process; the test run may use another binary/ini — advisory only.
$driver = \extension_loaded('xdebug') ? 'xdebug' : (\extension_loaded('pcov') ? 'pcov' : null);

// --- Report --------------------------------------------------------------------------------------

$yn = static fn(bool $b): string => $b ? 'yes' : 'NO';

echo "# Mutation testing pre-flight\n\n";
echo "Project root: `{$root}`\n\n";

echo "## Toolchain\n\n";
echo "| Component | Present | Source |\n|---|:---:|---|\n";
echo "| testo/testo                        | {$yn($testoInstalled)} | " . ($declared['testo/testo'] ?? '—') . " |\n";
echo "| infection/infection                | {$yn($infectionInstalled)} | " . ($declared['infection/infection'] ?? '—') . " |\n";
echo "| testo/bridge-infection             | {$yn($bridgeInstalled)} | " . ($declared['testo/bridge-infection'] ?? '—') . " |\n";
echo "| bridge registered with Infection   | {$yn($bridgeRegistered)} | extension-installer generated config |\n";
echo "| allow-plugins: extension-installer | {$yn($pluginAllowed)} | composer.json `config.allow-plugins` |\n";
echo "| infection.json with source dirs    | {$yn($configOk)} | " . ($configFile ?? 'no config file') . " |\n";
echo "| testFramework pinned to testo      | " . ($frameworkPinned ? 'yes' : 'no (advisory)') . " | "
    . ($configFile !== null ? 'testFramework=' . \var_export($testFramework, true) : '—') . " |\n";
echo "| coverage driver (this PHP)         | " . ($driver ?? 'NO') . " | xdebug/pcov |\n";
echo "\n";

if ($configOk) {
    echo 'Source dirs: ' . \implode(', ', \array_map(static fn(string $d): string => "`{$d}`", $sourceDirs)) . "\n";
    echo 'tmpDir: ' . ($tmpDir !== null ? "`{$tmpDir}`" : '(not set — Phase 2 falls back to `runtime` or `build`)') . "\n\n";
}

$fixes = [];
$testoInstalled or $fixes[] = 'Install Testo first: `composer require --dev testo/testo` (see `testo-configure`).';
$pluginAllowed or $fixes[] = '`composer config allow-plugins.infection/extension-installer true`';
($infectionInstalled && $bridgeInstalled) or $fixes[] = '`composer require --dev infection/infection testo/bridge-infection`';
($infectionInstalled && $bridgeInstalled && !$bridgeRegistered)
    and $fixes[] = 'Bridge installed but not registered — allow the plugin (above) and run `composer install` again.';
$configOk or $fixes[] = 'Create `infection.json` with `source.directories` — template in `references/setup.md`.';
$driver !== null or $fixes[] = 'No Xdebug/PCOV in this PHP. Install one, or point commands at a PHP that has it (`php -m | grep -i xdebug`).';

$ready = $testoInstalled && $infectionInstalled && $bridgeInstalled && $bridgeRegistered && $configOk;

if ($ready) {
    echo "**READY.** Proceed to Phase 1.\n";
    $driver === null and print "- Coverage driver not seen in this PHP — verify before Phase 3.\n";
    $frameworkPinned or print "- `testFramework` is not `testo` in {$configFile}; the skill passes `--test-framework=testo` on the CLI, "
        . "so this only matters for bare `vendor/bin/infection` runs. Set it when convenient.\n";
    exit(0);
}

echo "**NOT READY.** Offer the user to install and configure (steps in `references/setup.md`):\n\n";
foreach ($fixes as $fix) {
    echo "- {$fix}\n";
}
echo "\nRe-run this check after installing.\n";
exit(1);
