<?php

declare(strict_types=1);

/**
 * Generates the PHPUnit-mirror coverage that feeds the `infection-phpunit` mutation front, then
 * ALWAYS exits 0.
 *
 * Infection's own initial test run under-covers the generated mirror — it settles on core-only
 * coverage, so only core mutants are produced (see composer `infect:phpunit`). Running PHPUnit here
 * and handing Infection the report via `--coverage` + `--skip-initial-tests` instead covers the
 * plugins too.
 *
 * The step gates: if PHPUnit fails, so does this script, so a broken mirror (a conversion regression,
 * a newly non-convertible test) stops the run instead of feeding Infection a partial report. The
 * mirror is kept green for this — tests that only assert through Testo's fluent helpers are tolerated
 * via `beStrictAboutTestsThatDoNotTestAnything=false`, and the individually unconvertible ones are
 * skipped by the build's Rector passes.
 *
 * Usage: `php bin/phpunit-coverage.php [coverageDir]` (default runtime/phpunit-cov), run via the
 * composer script, not directly.
 */

$root = \str_replace('\\', '/', \dirname(__DIR__));
$covDir = $argv[1] ?? 'runtime/phpunit-cov';
$covDir = \rtrim(\str_replace('\\', '/', $covDir), '/');

$phpunit = $root . '/tools/phpunit/vendor/phpunit/phpunit/phpunit';

$command = \array_map('escapeshellarg', [
    \PHP_BINARY,
    $phpunit,
    '--configuration',
    $root . '/tools/phpunit/phpunit.xml',
    // Keep the report lean and reproducible: drop the per-file source token dump, and never let a
    // stale test-result cache reorder or skip cases (matches how Infection drives PHPUnit).
    '--exclude-source-from-xml-coverage',
    '--do-not-cache-result',
    '--coverage-xml=' . $root . '/' . $covDir . '/coverage-xml',
    '--log-junit=' . $root . '/' . $covDir . '/junit.xml',
]);

echo "Generating PHPUnit-mirror coverage into {$covDir}/\n";

$exit = 0;
\passthru(\implode(' ', $command), $exit);

$index = $root . '/' . $covDir . '/coverage-xml/index.xml';
if (!\is_file($index)) {
    \fwrite(\STDERR, "ERROR: no coverage report was written at {$index}\n");
    exit(1);
}

exit($exit);
