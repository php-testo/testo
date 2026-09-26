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
 *   php precheck.php [--scope=DIR]... [--root=PATH] [--phpunit-config=FILE]
 *
 * --scope=DIR            Restrict the test survey to this directory (repeatable). Default:
 *                        auto-detect common roots (`tests`, `test`) under --root.
 * --root=PATH            Project root to inspect (default: current working directory).
 * --phpunit-config=FILE  PHPUnit config to read (default: the first of `phpunit.xml`,
 *                        `phpunit.dist.xml`, `phpunit.xml.dist` under --root). Every other
 *                        `phpunit*.xml*` in the root is listed as an alternate config.
 *
 * It also lists the PHPUnit config settings Testo does not read (`<php>` ini/env/const,
 * `bootstrap`, `<testsuites>`, groups, extensions, source/coverage scope) as a
 * "carry over by hand" checklist, with the Testo-side counterpart where one exists.
 *
 * Reads only the filesystem (composer.json, vendor/, phpunit*.xml, the test files). Writes nothing.
 * Exit codes: 0 ok, 2 no composer.json / not a PHP project.
 */

$root = null;
$scopes = [];
$phpunitConfig = null;
foreach (\array_slice($argv, 1) as $arg) {
    if (\preg_match('/^--scope=(.+)$/', $arg, $m)) {
        $scopes[] = \rtrim($m[1], "/\\");
        continue;
    }
    if (\preg_match('/^--root=(.+)$/', $arg, $m)) {
        $root = $m[1];
        continue;
    }
    if (\preg_match('/^--phpunit-config=(.+)$/', $arg, $m)) {
        $phpunitConfig = $m[1];
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
$setDir = $path('vendor', 'testo', 'bridge-rector', 'config');
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

// --- PHPUnit XML config --------------------------------------------------------------------------

// PHPUnit's own lookup order when no --configuration is given.
$phpunitDefaults = ['phpunit.xml', 'phpunit.dist.xml', 'phpunit.xml.dist'];
$phpunitCandidates = \array_values(\array_unique(\array_merge(
    \array_filter($phpunitDefaults, $exists(...)),
    \array_map(\basename(...), \glob($root . '/phpunit*.xml*') ?: []),
)));
$phpunitConfig ??= $phpunitCandidates[0] ?? null;
$phpunitExtra = \array_values(\array_diff($phpunitCandidates, [$phpunitConfig]));

/**
 * Everything in the PHPUnit config that Testo does not read, grouped by report section.
 *
 * @var array<string, list<array{string, string}>> $carry section => list of [setting, Testo hint]
 */
$carry = [];
$phpunitError = null;

if ($phpunitConfig !== null) {
    $file = \is_file($phpunitConfig) ? $phpunitConfig : $path($phpunitConfig);
    \libxml_use_internal_errors(true);
    $xml = \is_file($file) ? \simplexml_load_file($file) : false;
    if ($xml === false) {
        $error = \libxml_get_errors()[0] ?? null;
        $phpunitError = \is_file($file)
            ? 'not valid XML' . ($error ? ': ' . \trim($error->message) : '')
            : 'file not found';
    } else {
        $attr = static fn(\SimpleXMLElement $e, string $name): ?string => isset($e[$name]) ? (string) $e[$name] : null;
        $q = static fn(?string $v): string => $v === null ? '' : '`' . $v . '`';
        $add = static function (string $section, string $setting, string $hint) use (&$carry): void {
            $carry[$section][] = [$setting, $hint];
        };

        // <php>: runtime state PHPUnit applies before loading any test.
        $superglobal = [
            'server' => '$_SERVER', 'var' => '$GLOBALS', 'get' => '$_GET', 'post' => '$_POST',
            'cookie' => '$_COOKIE', 'files' => '$_FILES', 'request' => '$_REQUEST',
        ];
        foreach ($xml->php?->children() ?? [] as $el) {
            $tag = $el->getName();
            $name = (string) ($el['name'] ?? '');
            $value = (string) ($el['value'] ?? $el);
            $export = \var_export($value, true);
            $pair = \var_export("{$name}={$value}", true);
            match (true) {
                $tag === 'ini' => $add('php', "ini {$name} = {$q($value)}", "`ini_set('{$name}', {$export});`"),
                $tag === 'env' && \filter_var((string) ($el['force'] ?? ''), \FILTER_VALIDATE_BOOLEAN) => $add(
                    'php',
                    "env {$name} = {$q($value)} (force)",
                    "`putenv({$pair}); \$_ENV['{$name}'] = \$_SERVER['{$name}'] = {$export};`",
                ),
                $tag === 'env' => $add(
                    'php',
                    "env {$name} = {$q($value)}",
                    "only when unset: `\\getenv('{$name}') === false and putenv({$pair});` (+ `\$_ENV`)",
                ),
                $tag === 'const' => $add('php', "const {$name} = {$q($value)}", "`\\defined('{$name}') or \\define('{$name}', {$export});`"),
                $tag === 'includePath' => $add('php', "includePath {$q($value)}", "`set_include_path({$export} . PATH_SEPARATOR . get_include_path());`"),
                isset($superglobal[$tag]) => $add('php', "{$tag} {$name} = {$q($value)}", "`{$superglobal[$tag]}['{$name}'] = {$export};`"),
                default => $add('php', "`<{$tag}>` {$name}", 'unknown `<php>` entry: carry over by hand'),
            };
        }

        // Root attributes that change how the run behaves.
        $rootHints = [
            'bootstrap' => 'no bootstrap option: `require_once __DIR__ . \'/<file>\';` at the top of testo.php',
            'executionOrder' => 'no counterpart: Testo runs tests in declaration order',
            'failOnRisky' => 'no switch: a risky test already makes the run non-green',
            'failOnWarning' => 'no counterpart: Testo has no Warning status',
            'beStrictAboutOutputDuringTests' => 'no counterpart',
            'processIsolation' => 'no counterpart: all tests run in one process',
            'cacheDirectory' => 'drop: Testo keeps no result cache',
            'cacheResult' => 'drop: Testo keeps no result cache',
        ];
        foreach ($rootHints as $name => $hint) {
            ($v = $attr($xml, $name)) === null or $add('root', "{$name}=\"{$v}\"", $hint);
        }

        // <testsuites>: paths are relative to the config file, as in testo.php.
        foreach ($xml->testsuites?->testsuite ?? [] as $suite) {
            $name = $attr($suite, 'name') ?? '(unnamed)';
            $items = [];
            $paths = ['include' => [], 'exclude' => []];
            foreach ($suite->children() as $el) {
                $tag = $el->getName();
                $dir = \trim((string) $el);
                $paths[$tag === 'exclude' ? 'exclude' : 'include'][] = \var_export($dir, true);
                $extra = [];
                foreach (['prefix', 'suffix', 'phpVersion', 'groups'] as $a) {
                    ($v = $attr($el, $a)) === null or $extra[] = "{$a}=\"{$v}\"";
                }
                $items[] = "{$tag} `{$dir}`" . ($extra === [] ? '' : ' (' . \implode(' ', $extra) . ')');
            }
            $finder = 'include: [' . \implode(', ', $paths['include']) . ']'
                . ($paths['exclude'] === [] ? '' : ', exclude: [' . \implode(', ', $paths['exclude']) . ']');
            $add(
                'suites',
                "**{$name}**: " . (\implode('; ', $items) ?: '(empty)'),
                '`new SuiteConfig(name: ' . \var_export($name, true) . ", location: new FinderConfig({$finder}))`",
            );
        }

        foreach (['include', 'exclude'] as $mode) {
            foreach ($xml->groups?->{$mode}?->group ?? [] as $g) {
                $add('groups', "{$mode} group `{$g}`", $mode === 'include' ? "`--group={$g}` on the CLI" : "`--group=!{$g}` on the CLI");
            }
        }

        // PHPUnit 10+ <extensions><bootstrap class>, PHPUnit ≤9 <extensions><extension class> and <listeners>.
        foreach ([$xml->extensions?->children() ?? [], $xml->listeners?->children() ?? []] as $list) {
            foreach ($list as $el) {
                $params = [];
                foreach ($el->parameter ?? [] as $p) {
                    $params[] = $p['name'] . '=' . $p['value'];
                }
                $add(
                    'extensions',
                    "`<{$el->getName()}>` `" . ($attr($el, 'class') ?? '?') . '`' . ($params === [] ? '' : ' (' . \implode(', ', $params) . ')'),
                    '**no counterpart**: re-implement as a Testo plugin (testo-plugin-author skill) or drop',
                );
            }
        }

        // PHPUnit 10+ keeps the source scope in <source>, PHPUnit 9 in <coverage>.
        foreach (['source', 'coverage'] as $section) {
            foreach (['include', 'exclude'] as $mode) {
                foreach ($xml->{$section}?->{$mode}?->children() ?? [] as $el) {
                    $suffix = $attr($el, 'suffix');
                    $add(
                        'source',
                        "`<{$section}>` {$mode} {$el->getName()} `" . \trim((string) $el) . '`' . ($suffix === null ? '' : " (suffix=\"{$suffix}\")"),
                        "`ApplicationConfig(src: new FinderConfig({$mode}: [...]))`",
                    );
                }
            }
            foreach ($xml->{$section}?->report?->children() ?? [] as $el) {
                $out = $attr($el, 'outputFile') ?? $attr($el, 'outputDirectory') ?? '';
                $add('source', "coverage report {$el->getName()} `{$out}`", 'report of `CodecovPlugin` (testo-coverage skill)');
            }
        }
        foreach ($xml->logging?->children() ?? [] as $el) {
            $out = $attr($el, 'outputFile') ?? '';
            $add(
                'source',
                "logging {$el->getName()} `{$out}`",
                $el->getName() === 'junit' ? '`new JUnitPlugin(...)` in plugins or `--log-junit=<file>`' : 'no direct counterpart',
            );
        }
    }
}

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
    echo "- `exception_regex` — `expectExceptionMessageMatches`: Rector converts it to `->withMessageMatchingRegex()`; only a leftover needs a hand port.\n";
    echo "- `incomplete` — `markTestIncomplete`: Testo has no Incomplete status.\n";
}

echo "\n## PHPUnit config: carry over by hand\n\n";
if ($phpunitConfig === null) {
    echo "No `phpunit.xml`, `phpunit.dist.xml` or `phpunit.xml.dist` under the root. "
        . "Pass --phpunit-config=FILE if it lives elsewhere.\n";
} elseif ($phpunitError !== null) {
    echo "`{$phpunitConfig}`: {$phpunitError}. Nothing read from it.\n";
} else {
    echo "Read `{$phpunitConfig}`. Testo ignores it: each setting below is lost unless it moves into "
        . "`testo.php` (included in the test process, before discovery) or a file it requires.\n\n";
    $sections = [
        'php' => '`<php>` runtime settings: top of testo.php',
        'root' => 'Run options (`<phpunit>` attributes)',
        'suites' => '`<testsuites>`: one `SuiteConfig` each',
        'groups' => '`<groups>`: no config counterpart, pass on the CLI or in a composer script',
        'extensions' => 'Extensions and listeners',
        'source' => 'Source scope, coverage and logging',
    ];
    $carry === [] and print("Nothing to carry over.\n");
    $cell = static fn(string $s): string => \str_replace('|', '\|', $s);
    foreach ($sections as $key => $title) {
        if (!isset($carry[$key])) {
            continue;
        }
        echo "### {$title}\n\n| Setting | Testo side |\n|---|---|\n";
        foreach ($carry[$key] as [$setting, $hint]) {
            echo "| {$cell($setting)} | {$cell($hint)} |\n";
        }
        echo "\n";
        $key === 'suites' and print(
            "`FinderConfig` takes existing paths only (no globs) and has no prefix/suffix filter. Tests marked "
            . "`#[Test]` are found in any file; name-based discovery (`*Test.php`, `test*` methods) is "
            . "`Testo\\Convention\\NamingConventionPlugin(caseSuffix: 'Test')` in the suite's plugins. "
            . "Exclude fixture dirs that hold test-looking code.\n\n"
        );
    }
}
$phpunitExtra === [] or print(
    "Alternate configs (re-run with --phpunit-config=FILE to list each): `"
    . \implode('`, `', $phpunitExtra) . "`.\n"
);

echo "\n## Note\n\n";
echo "Even on the Rector path, which detaches `TestCase` and adds `#[Test]`, mocks, PHPUnit-only "
    . "attributes and tests inherited from a vendor base are left over, so an AI/human pass is always "
    . "required to finish. See references/migrate-with-rector.md.\n";

exit(0);
