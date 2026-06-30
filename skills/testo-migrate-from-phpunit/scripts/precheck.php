<?php

declare(strict_types=1);

/**
 * Migration pre-flight check: detect tooling and survey the PHPUnit test surface.
 *
 * Run this in Phase 3 of testo-migrate-from-phpunit. It answers two questions the
 * orchestrator needs before choosing an approach:
 *   1. Can we use the Rector path? (is `testo/bridge-rector` — and therefore Rector — installed?)
 *   2. What is the migration scope? (which dirs hold PHPUnit tests, how many, and which
 *      hard-to-convert constructs they use: mocks, constraints, regex exception messages, …)
 *
 * Usage:
 *   php precheck.php [--scope=DIR]... [--root=PATH]
 *
 * --scope=DIR   Restrict the test survey to this directory (repeatable). Default: auto-detect
 *               common roots (`tests`, `test`) under --root.
 * --root=PATH   Project root to inspect (default: current working directory).
 *
 * Reads only the filesystem (composer.json, vendor/, the test files). Writes nothing.
 * Exit codes: 0 ok, 2 no composer.json / not a PHP project.
 */

$root = null;
$scopes = [];
foreach (\array_slice($argv, 1) as $arg) {
    if (\preg_match('/^--scope=(.+)$/', $arg, $m)) {
        $scopes[] = \rtrim($m[1], "/\\");
        continue;
    }
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

// --- Tooling detection ---------------------------------------------------------------------------

$composer = \json_decode((string) \file_get_contents($path('composer.json')), true) ?: [];
$declared = \array_merge($composer['require'] ?? [], $composer['require-dev'] ?? []);

// A binary may ship as `bin` (POSIX) or `bin.bat`/`bin` (Windows shim) — accept any.
$hasBin = static fn(string $name): bool => $exists('vendor', 'bin', $name)
    || $exists('vendor', 'bin', $name . '.bat');

$testoInstalled  = $exists('vendor', 'testo', 'testo') || $hasBin('testo');
$bridgeInstalled = $exists('vendor', 'testo', 'bridge-rector');
$rectorInstalled = $hasBin('rector') || $exists('vendor', 'rector', 'rector');

// The bridge requires rector/rector, so installing the bridge alone is enough for the Rector path.
$rectorReady = $bridgeInstalled && $rectorInstalled;

// Locate the bridge's conversion sets, if present, so the scaffolder can wire them.
$setDir = $path('vendor', 'testo', 'bridge-rector', 'config', 'sets');
$sets = $bridgeInstalled && \is_dir($setDir)
    ? \array_map(static fn(string $f): string => \basename($f, '.php'), \glob($setDir . '/*.php') ?: [])
    : [];

$rectorConfigs = \array_values(\array_filter(
    ['rector.php', 'rector-testo.php', 'rector-migration.php'],
    $exists(...),
));

// --- Test surface survey -------------------------------------------------------------------------

if ($scopes === []) {
    foreach (['tests', 'test'] as $candidate) {
        $exists($candidate) && \is_dir($path($candidate)) and $scopes[] = $candidate;
    }
}

// Markers we look for, grouped by what they imply for the migration.
$markers = [
    // structural — the test will not be discovered by Testo until this is resolved
    'extends_testcase'   => '/\bextends\s+(?:\\\\?PHPUnit\\\\Framework\\\\)?TestCase\b/',
    'phpunit_namespace'  => '/\bPHPUnit\\\\Framework\b/',
    // mechanical — the Rector phpunit-to-testo set converts these
    'assert_calls'       => '/\$this->assert\w+\s*\(/',
    'expect_exception'   => '/\$this->expectException\s*\(/',
    'data_provider'      => '/@dataProvider\b|#\[\s*(?:\\\\?PHPUnit\\\\Framework\\\\Attributes\\\\)?DataProvider\b/',
    'lifecycle'          => '/\bfunction\s+(?:setUp|tearDown|setUpBeforeClass|tearDownAfterClass)\s*\(/',
    'groups'             => '/@group\b|#\[\s*(?:\\\\?PHPUnit\\\\Framework\\\\Attributes\\\\)?Group\b/',
    // hard — no faithful Rector rule; needs an AI / human decision (see references)
    'mocks'              => '/->(?:createMock|createStub|getMockBuilder|getMock|prophesize)\s*\(/',
    'assert_that'        => '/\$this->assertThat\s*\(/',
    'exception_regex'    => '/\$this->expectExceptionMessageMatches\s*\(/',
    'incomplete'         => '/\$this->markTestIncomplete\s*\(/',
];

/** @var array<string, array{files:int, tests:int, markers:array<string,int>}> $byDir */
$byDir = [];
$totalFiles = 0;
$totalTests = 0;

foreach ($scopes as $scope) {
    $dir = $path($scope);
    if (!\is_dir($dir)) {
        \fwrite(\STDERR, "warning: scope '{$scope}' is not a directory under {$root}, skipping\n");
        continue;
    }

    $it = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
    );

    /** @var \SplFileInfo $file */
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $code = (string) \file_get_contents($file->getPathname());

        // Only count files that look like PHPUnit tests.
        if (!\preg_match($markers['phpunit_namespace'], $code) && !\preg_match($markers['extends_testcase'], $code)) {
            continue;
        }

        // Group under the first path segment below the scope (e.g. tests/Unit, tests/Feature).
        $rel = \str_replace('\\', '/', \substr($file->getPathname(), \strlen($dir) + 1));
        $bucket = $scope . '/' . (\str_contains($rel, '/') ? \explode('/', $rel)[0] : '');
        $bucket = \rtrim($bucket, '/');

        $byDir[$bucket] ??= ['files' => 0, 'tests' => 0, 'markers' => []];
        $byDir[$bucket]['files']++;
        $totalFiles++;

        $tests = \preg_match_all('/\bfunction\s+test\w*\s*\(/', $code)
            + \preg_match_all('/@test\b/', $code)
            + \preg_match_all('/#\[\s*(?:\\\\?PHPUnit\\\\Framework\\\\Attributes\\\\)?Test\b/', $code);
        $byDir[$bucket]['tests'] += $tests;
        $totalTests += $tests;

        foreach ($markers as $name => $re) {
            if (\preg_match($re, $code)) {
                $byDir[$bucket]['markers'][$name] = ($byDir[$bucket]['markers'][$name] ?? 0) + 1;
            }
        }
    }
}

\ksort($byDir);

// --- Report --------------------------------------------------------------------------------------

$yn = static fn(bool $b): string => $b ? 'yes' : 'NO';

echo "# PHPUnit → Testo migration pre-flight\n\n";
echo "Project root: `{$root}`\n\n";

echo "## Tooling\n\n";
echo "| Component | Present | Source |\n|---|:---:|---|\n";
echo "| testo/testo            | {$yn($testoInstalled)}  | " . ($declared['testo/testo'] ?? '—') . " |\n";
echo "| testo/bridge-rector    | {$yn($bridgeInstalled)} | " . ($declared['testo/bridge-rector'] ?? '—') . " |\n";
echo "| rector/rector          | {$yn($rectorInstalled)} | " . ($declared['rector/rector'] ?? '(pulled by bridge)') . " |\n";
echo "| existing rector config | " . ($rectorConfigs === [] ? 'NO' : \implode(', ', $rectorConfigs)) . " |  |\n";
echo "\n";

echo $rectorReady
    ? "**RECTOR PATH: AVAILABLE.** Conversion sets found: " . \implode(', ', $sets) . "\n\n"
    : "**RECTOR PATH: NOT READY.** Install with: `composer require --dev testo/bridge-rector` "
        . "(pulls in rector/rector). Until then, only the AI-agent path is available.\n\n";

echo "## Test surface\n\n";
if ($byDir === []) {
    echo "No PHPUnit-looking test files found in: " . (\implode(', ', $scopes) ?: '(no scope)') . ".\n";
    echo "Pass --scope=DIR for a non-standard test directory.\n";
} else {
    echo "Scanned: " . \implode(', ', $scopes) . " — **{$totalFiles} PHPUnit files, ~{$totalTests} tests**.\n\n";
    echo "| Directory | Files | Tests | Hard constructs (need decisions) |\n|---|--:|--:|---|\n";
    foreach ($byDir as $dir => $info) {
        $hard = [];
        foreach (['mocks', 'assert_that', 'exception_regex', 'incomplete'] as $h) {
            isset($info['markers'][$h]) and $hard[] = "{$h}×{$info['markers'][$h]}";
        }
        echo "| `{$dir}` | {$info['files']} | {$info['tests']} | " . (\implode(', ', $hard) ?: '—') . " |\n";
    }
    echo "\n";
    echo "Legend for hard constructs (no faithful Rector rule — see references):\n";
    echo "- `mocks` — `createMock`/`getMockBuilder`/`prophesize`: Testo ships no mocking; hand-roll fakes or keep a mock lib.\n";
    echo "- `assert_that` — PHPUnit constraint objects: no Testo equivalent.\n";
    echo "- `exception_regex` — `expectExceptionMessageMatches`: Testo matches substrings, not PCRE.\n";
    echo "- `incomplete` — `markTestIncomplete`: Testo has no Incomplete status.\n";
}

echo "\n## Note\n\n";
echo "Even on the Rector path, removing `extends TestCase` and reconciling test discovery "
    . "(`#[Test]` / dropping the `test` prefix) is a **structural** step Rector does not perform — "
    . "an AI/human pass is always required to finish. See references/migrate-with-rector.md.\n";

exit(0);
