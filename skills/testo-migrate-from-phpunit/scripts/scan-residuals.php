<?php

declare(strict_types=1);

/**
 * Survey test files for everything that still needs an AI/human decision, and write batched
 * work-lists for the subagent porting pass. Used by both migration paths:
 *   - Rector path: run it AFTER Rector to find the structural residue Rector cannot convert
 *     (still `extends TestCase`, mocks, constraints, …).
 *   - AI-only path: run it FIRST to plan the file-by-file port.
 *
 * Usage:
 *   php scan-residuals.php --scope=DIR [--scope=DIR]... [--out=DIR] [--batch=N] [--root=PATH]
 *
 * --scope=DIR   Directory to scan (repeatable). Default: tests, test (whichever exist).
 * --out=DIR     Where to write the report + batches (default: `runtime` if it exists, else `build`).
 * --batch=N     Files per batch work-list (default 8).
 * --root=PATH   Project root (default: cwd).
 *
 * Writes:
 *   <out>/migration-report.md            ranked summary + batch index (read this first)
 *   <out>/migration-batches/NNN.json     per-file work-lists for the subagent pass
 *
 * Each batch entry: { path, tests, needs[], hints{} } where needs[] is the ordered to-do list.
 * Exit codes: 0 ok, 1 usage, 2 no files found.
 */

$scopes = [];
$out = null;
$batch = 8;
$root = null;
foreach (\array_slice($argv, 1) as $arg) {
    if (\preg_match('/^--scope=(.+)$/', $arg, $m)) {
        $scopes[] = \rtrim($m[1], "/\\");
    } elseif (\preg_match('/^--out=(.+)$/', $arg, $m)) {
        $out = \rtrim($m[1], "/\\");
    } elseif (\preg_match('/^--batch=(\d+)$/', $arg, $m)) {
        $batch = \max(1, (int) $m[1]);
    } elseif (\preg_match('/^--root=(.+)$/', $arg, $m)) {
        $root = $m[1];
    } else {
        \fwrite(\STDERR, "unknown argument: {$arg}\n");
    }
}

$root = \rtrim($root ?? \getcwd(), "/\\");

if ($scopes === []) {
    foreach (['tests', 'test'] as $candidate) {
        \is_dir($root . '/' . $candidate) and $scopes[] = $candidate;
    }
}
if ($scopes === []) {
    \fwrite(\STDERR, "No scope. Pass --scope=DIR.\n");
    exit(1);
}

if ($out === null) {
    $out = \is_dir($root . '/runtime') ? 'runtime' : 'build';
}
$outAbs = $root . '/' . $out;
\is_dir($outAbs) or @\mkdir($outAbs, 0o777, true);
$batchDir = $outAbs . '/migration-batches';
\is_dir($batchDir) or @\mkdir($batchDir, 0o777, true);

/*
 * Each check: a regex + the to-do line the subagent must act on + a one-line hint.
 * Ordered structural-first: the base-class / discovery work must happen before the file is
 * discoverable by Testo at all, so it leads the per-file `needs` list.
 */
$checks = [
    'extends_testcase' => [
        're'   => '/\bextends\s+(?:\\\\?PHPUnit\\\\Framework\\\\)?TestCase\b/',
        'need' => 'Remove `extends TestCase`; add class-level `#[Test]` (or per-method) and drop the `test` prefix on methods.',
        'hint' => 'Testo requires no base class; discovery is attribute-based.',
    ],
    'phpunit_test_attr' => [
        're'   => '/#\[\s*(?:\\\\?PHPUnit\\\\Framework\\\\Attributes\\\\)?Test\b|@test\b|\bfunction\s+test[A-Z0-9_]/',
        'need' => 'Convert PHPUnit test markers to Testo `#[Test]`; rename `testFoo()` → `foo()`.',
        'hint' => 'Class-level `#[Test]` is preferred when every public method is a test.',
    ],
    'mocks' => [
        're'   => '/->(?:createMock|createStub|getMockBuilder|getMock|prophesize)\s*\(/',
        'need' => 'Replace PHPUnit mocks with a hand-rolled fake (preferred) or a kept mock library. Never mock final classes/enums.',
        'hint' => 'Testo ships no mocking. See the "Mocks" row of the map.',
    ],
    'assert_that' => [
        're'   => '/\$this->assertThat\s*\(/',
        'need' => 'Rewrite `assertThat($v, $constraint)` as explicit `Assert::*` calls; there is no Testo constraint object.',
        'hint' => 'Decompose the constraint into concrete assertions.',
    ],
    'exception_regex' => [
        're'   => '/\$this->expectExceptionMessageMatches\s*\(/',
        'need' => 'Replace regex message matching with `Expect::exception()->withMessageContaining(substring)` if a literal substring suffices; otherwise assert the caught message manually.',
        'hint' => 'Testo matches substrings, not PCRE.',
    ],
    'incomplete' => [
        're'   => '/\$this->markTestIncomplete\s*\(/',
        'need' => 'Port `markTestIncomplete` to `throw new SkipTest(\'TODO: …\')` or leave the body empty (reported Risky).',
        'hint' => 'Testo has no Incomplete status.',
    ],
    'leftover_assert' => [
        're'   => '/\$this->assert\w+\s*\(/',
        'need' => 'Convert remaining `$this->assert*` calls to `Assert::*` (mind the actual/expected order flip).',
        'hint' => 'Rector normally handles these — leftovers mean Rector was not run on this file or hit an edge case.',
    ],
    'leftover_expect' => [
        're'   => '/\$this->expect(?:Exception|NotToPerformAssertions)\w*\s*\(/',
        'need' => 'Convert remaining `$this->expect*` calls to `Expect::exception(...)` / `#[ExpectNoAssertions]`.',
        'hint' => 'Rector converts the bare forms; fluent message/code may remain.',
    ],
];

/** @var list<array{path:string, tests:int, needs:list<string>, hints:array<string,string>}> $files */
$files = [];
$tally = \array_fill_keys(\array_keys($checks), 0);

foreach ($scopes as $scope) {
    $dir = $root . '/' . $scope;
    if (!\is_dir($dir)) {
        \fwrite(\STDERR, "warning: scope '{$scope}' not a directory, skipping\n");
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
        $needs = [];
        $hints = [];
        foreach ($checks as $name => $check) {
            if (\preg_match($check['re'], $code)) {
                $needs[] = $check['need'];
                $hints[$name] = $check['hint'];
                $tally[$name]++;
            }
        }

        if ($needs === []) {
            continue;
        }

        $rel = \str_replace('\\', '/', \substr($file->getPathname(), \strlen($root) + 1));
        $tests = \preg_match_all('/\bfunction\s+\w+\s*\([^)]*\)\s*:/', $code);
        $files[] = ['path' => $rel, 'tests' => $tests, 'needs' => $needs, 'hints' => $hints];
    }
}

if ($files === []) {
    \fwrite(\STDERR, "No files needing manual work found in: " . \implode(', ', $scopes) . ".\n");
    \fwrite(\STDERR, "If you already ran Rector, the mechanical part may be done — verify with `vendor/bin/testo`.\n");
    exit(2);
}

// Most-needs first: files with the longest to-do list are the heaviest ports.
\usort($files, static fn(array $a, array $b): int => \count($b['needs']) <=> \count($a['needs']));

// --- Batches ---
$batches = \array_chunk($files, $batch);
$index = [];
foreach ($batches as $i => $chunk) {
    $name = \sprintf('%03d.json', $i + 1);
    \file_put_contents(
        $batchDir . '/' . $name,
        (string) \json_encode($chunk, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
    );
    $index[] = ['file' => "migration-batches/{$name}", 'count' => \count($chunk)];
}

// --- Report ---
$label = [
    'extends_testcase'   => 'extends TestCase (structural)',
    'phpunit_test_attr'  => 'PHPUnit test markers',
    'mocks'              => 'mocks',
    'assert_that'        => 'assertThat constraints',
    'exception_regex'    => 'regex exception message',
    'incomplete'         => 'markTestIncomplete',
    'leftover_assert'    => 'leftover $this->assert*',
    'leftover_expect'    => 'leftover $this->expect*',
];

$md = "# PHPUnit → Testo migration work-list\n\n";
$md .= "Scanned: " . \implode(', ', $scopes) . " — **" . \count($files) . " files need work**, in "
    . \count($batches) . " batch(es) of up to {$batch}.\n\n";

$md .= "## Residual constructs (files affected)\n\n";
$md .= "| Construct | Files |\n|---|--:|\n";
\arsort($tally);
foreach ($tally as $name => $count) {
    $count > 0 and $md .= "| " . $label[$name] . " | {$count} |\n";
}
$md .= "\n";

$md .= "## Files (most work first)\n\n";
$md .= "| File | Tests | To-do items |\n|---|--:|--:|\n";
foreach ($files as $f) {
    $md .= "| `{$f['path']}` | {$f['tests']} | " . \count($f['needs']) . " |\n";
}
$md .= "\n## Batches\n\n";
foreach ($index as $b) {
    $md .= "- `{$b['file']}` — {$b['count']} files\n";
}
$md .= "\nFeed each batch to the porting subagents (references/subagent-port-prompt.md). "
    . "Files are independent, so a batch may be run in PARALLEL — see the skill's Phase notes.\n";

\file_put_contents($outAbs . '/migration-report.md', $md);

echo "Wrote {$out}/migration-report.md\n";
echo "Wrote " . \count($batches) . " batch(es) to {$out}/migration-batches/\n";
echo \count($files) . " files need work.\n";

exit(0);
