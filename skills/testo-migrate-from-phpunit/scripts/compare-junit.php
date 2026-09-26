<?php

declare(strict_types=1);

/**
 * Compare a PHPUnit JUnit report with a Testo JUnit report, test method by test method.
 *
 * Run this in Phase 5 of testo-migrate-from-phpunit. Equal totals can hide a dropped test behind an
 * extra one elsewhere; this script counts how many runs (data sets included) each runner reported
 * for every `Class::method` and lists every method whose count differs.
 *
 * Usage:
 *   php compare-junit.php <phpunit-junit.xml> <testo-junit.xml> [--strip-test-prefix] [--map=FROM=TO]...
 *
 * --strip-test-prefix  Treat `testFooBar` / `test_foo_bar` and `fooBar` / `foo_bar` as the same method,
 *                      for ports that renamed test methods after dropping the `test` prefix.
 * --map=FROM=TO        Rewrite a namespace prefix in the PHPUnit class names before comparing
 *                      (repeatable), for ports that moved the tests to another namespace.
 *                      Example: --map='Tests\Unit\=Tests\'
 *
 * Produce the inputs with `vendor/bin/phpunit --log-junit=<file>` and
 * `vendor/bin/testo --log-junit=<file>` over the same scope.
 *
 * Method names are compared exactly, although PHP treats them case-insensitively; a pair differing
 * only in case is flagged in the output.
 *
 * Exit codes: 0 identical per-method counts, 1 at least one mismatch, 2 usage or I/O error.
 */

const TESTO_NS = 'https://php-testo.github.io/schema/junit/1';

$files = [];
$stripPrefix = false;
$maps = [];
foreach (\array_slice($argv, 1) as $arg) {
    if ($arg === '--strip-test-prefix') {
        $stripPrefix = true;
    } elseif (\preg_match('/^--map=(.+?)=(.*)$/', $arg, $m)) {
        $maps[\ltrim($m[1], '\\')] = \ltrim($m[2], '\\');
    } elseif (\str_starts_with($arg, '--')) {
        \fwrite(\STDERR, "unknown argument: {$arg}\n");
        exit(2);
    } else {
        $files[] = $arg;
    }
}

if (\count($files) !== 2) {
    \fwrite(\STDERR, "Usage: php compare-junit.php <phpunit-junit.xml> <testo-junit.xml> [--strip-test-prefix] [--map=FROM=TO]...\n");
    exit(2);
}

$load = static function (string $file): \SimpleXMLElement {
    if (!\is_file($file) || !\is_readable($file)) {
        \fwrite(\STDERR, "Cannot read {$file}\n");
        exit(2);
    }
    \libxml_use_internal_errors(true);
    $xml = \simplexml_load_file($file);
    if ($xml === false) {
        $error = \libxml_get_errors()[0] ?? null;
        \fwrite(\STDERR, "Invalid XML in {$file}" . ($error ? ': ' . \trim($error->message) : '') . "\n");
        exit(2);
    }
    return $xml;
};

$method = static function (string $name) use ($stripPrefix): string {
    $stripPrefix and $name = \lcfirst((string) \preg_replace('/^test_?(?=\w)/', '', $name));
    return $name;
};

/**
 * @return array{runs: array<string, int>, skipped: array<string, int>, cases: int, skippedTotal: int, failed: int}
 */
$tally = static function (\SimpleXMLElement $xml, callable $key): array {
    $out = ['runs' => [], 'skipped' => [], 'cases' => 0, 'skippedTotal' => 0, 'failed' => 0];
    foreach ($xml->xpath('//testcase') ?: [] as $case) {
        $k = $key($case);
        $out['runs'][$k] = ($out['runs'][$k] ?? 0) + 1;
        $out['cases']++;
        if (isset($case->skipped)) {
            $out['skipped'][$k] = ($out['skipped'][$k] ?? 0) + 1;
            $out['skippedTotal']++;
        }
        (isset($case->failure) || isset($case->error)) and $out['failed']++;
    }
    return $out;
};

// PHPUnit 9-13: `class="Foo\BarTest"` plus a dotted `classname="Foo.BarTest"`; older writers only
// have the dotted one. Data rows are `name with data set #0` or `name with data set "label"`.
$phpunitKey = static function (\SimpleXMLElement $case) use ($maps, $method): string {
    $class = (string) ($case['class'] ?? '');
    $class === '' and $class = \str_replace('.', '\\', (string) ($case['classname'] ?? ''));
    $class = \ltrim($class, '\\');
    foreach ($maps as $from => $to) {
        if (\str_starts_with($class, $from)) {
            $class = $to . \substr($class, \strlen($from));
            break;
        }
    }
    $name = (string) \preg_replace('/ with data set (?:#\d+|".*")$/s', '', (string) $case['name']);
    return $class . '::' . $method($name);
};

// Testo: `classname` is the FQCN, or the function FQN for a free-function test. A data row is
// `name [key]` or `name [provider:key]` and carries `testo:data-set`; the key itself may hold
// brackets, so the suffix is cut at the first ` [`, which a PHP identifier never contains.
$testoKey = static function (\SimpleXMLElement $case) use ($method): string {
    $class = \ltrim((string) ($case['classname'] ?? ''), '\\');
    $name = (string) $case['name'];
    if (isset($case->attributes(TESTO_NS)['data-set']) && ($pos = \strpos($name, ' [')) !== false) {
        $name = \substr($name, 0, $pos);
    }
    return $class . '::' . $method($name);
};

$phpunit = $tally($load($files[0]), $phpunitKey);
$testo = $tally($load($files[1]), $testoKey);

$mismatch = [];
$missing = [];
foreach ($phpunit['runs'] as $k => $n) {
    $t = $testo['runs'][$k] ?? 0;
    if ($t === 0) {
        $missing[$k] = $n;
    } elseif ($t !== $n) {
        $mismatch[$k] = [$n, $t];
    }
}
$extra = \array_diff_key($testo['runs'], $phpunit['runs']);

$lower = [];
foreach (\array_keys($extra) as $k) {
    $lower[\strtolower($k)] = $k;
}
$caseHint = static fn(string $k): string => isset($lower[\strtolower($k)])
    ? " (differs only in case from `{$lower[\strtolower($k)]}`)"
    : '';

$isFunction = static function (string $k): bool {
    [$owner, $name] = \explode('::', $k, 2) + [1 => ''];
    return $owner === $name || \str_ends_with($owner, '\\' . $name);
};

echo "# PHPUnit vs Testo: per-method run counts\n\n";
echo "| | PHPUnit | Testo |\n|---|--:|--:|\n";
echo "| test methods | " . \count($phpunit['runs']) . " | " . \count($testo['runs']) . " |\n";
echo "| runs (incl. data sets) | {$phpunit['cases']} | {$testo['cases']} |\n";
echo "| skipped | {$phpunit['skippedTotal']} | {$testo['skippedTotal']} |\n";
echo "| failed / errored | {$phpunit['failed']} | {$testo['failed']} |\n\n";

if ($missing === [] && $mismatch === [] && $extra === []) {
    echo "**IDENTICAL.** Every method ran the same number of times under both runners.\n";
    $phpunit['skippedTotal'] !== $testo['skippedTotal']
        and print("Skipped counts differ: check skip conditions that depend on the runner or the environment.\n");
    exit(0);
}

if ($missing !== []) {
    echo "## Missing in Testo (" . \count($missing) . ")\n\n";
    echo "Not discovered: usually still `extends TestCase`, no `#[Test]`, or outside the suite location.\n\n";
    foreach ($missing as $k => $n) {
        echo "- `{$k}` phpunit={$n}{$caseHint($k)}\n";
    }
    echo "\n";
}

if ($mismatch !== []) {
    echo "## Run count differs (" . \count($mismatch) . ")\n\n";
    echo "Usually a data provider/data set that lost or gained rows.\n\n";
    foreach ($mismatch as $k => [$p, $t]) {
        $skip = ($phpunit['skipped'][$k] ?? 0) . '/' . ($testo['skipped'][$k] ?? 0);
        echo "- `{$k}` phpunit={$p} testo={$t} (skipped {$skip})\n";
    }
    echo "\n";
}

if ($extra !== []) {
    echo "## Only in Testo (" . \count($extra) . ")\n\n";
    echo "Renamed methods, new tests, or a provider method picked up as a test.\n\n";
    foreach ($extra as $k => $n) {
        echo "- `{$k}` testo={$n}" . ($isFunction($k) ? ' (function test)' : '') . "\n";
    }
    echo "\n";
}

echo "**MISMATCH.** " . \count($missing) . " missing, " . \count($mismatch) . " differing, "
    . \count($extra) . " extra.\n";
exit(1);
