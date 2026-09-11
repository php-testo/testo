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
 * A handful of converted mirror tests carry no PHPUnit assertions yet (Testo's fluent expectation
 * DSL does not fully convert), so PHPUnit exits non-zero even though the coverage report is written
 * in full. This step must therefore not gate the run: the report is what matters, and Infection
 * re-derives correctness from the mutants, not from this run's pass/fail. Hence the unconditional
 * exit 0.
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

echo "Generating PHPUnit-mirror coverage into {$covDir}/ (best-effort; failures are ignored)\n";

$exit = 0;
\passthru(\implode(' ', $command), $exit);

$index = $root . '/' . $covDir . '/coverage-xml/index.xml';
if (!\is_file($index)) {
    \fwrite(\STDERR, "ERROR: no coverage report was written at {$index}\n");
    exit(1);
}

echo "\nPHPUnit exited with {$exit}; coverage report written — continuing.\n";
exit(0);
