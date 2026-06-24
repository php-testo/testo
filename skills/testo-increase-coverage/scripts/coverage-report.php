<?php

declare(strict_types=1);

/**
 * Aggregate a Clover coverage report into an LLM-friendly summary + batched work-lists,
 * optionally enriched with "which tests already cover this file" from a PHPUnit coverage-xml
 * directory and a JUnit log.
 *
 * Usage:
 *   php coverage-report.php <clover.xml> [--coverage-xml=DIR] [--log-junit=FILE]
 *       [--threshold=N] [--scope=SUBSTR] [--batch=N] [--root=PATH]
 *
 * The three input flags deliberately mirror the testo CLI flags that produce the artifacts in
 * Phase 2 (--coverage-clover / --coverage-xml / --log-junit) — pass the same paths back here.
 *
 * <clover.xml>        The Clover report from Phase 2 (testo --coverage-clover=...). Source of the
 *                     full statement set, so it alone yields the *uncovered* line ranges.
 * --coverage-xml=DIR  The PHPUnit coverage-xml directory (testo --coverage-xml=...). Adds, per file,
 *                     the test classes that already execute it (the `<covered by="Class::m"/>`
 *                     attribution). coverage-xml lists only covered lines, hence not used for ranges.
 * --log-junit=FILE    The JUnit log (testo --log-junit=...). Maps each test class to its test file and
 *                     its suite name (the test "type", e.g. "Tokenizer/Unit"), so the work-list can
 *                     point at the exact existing test file to extend.
 * --threshold=N  Only files BELOW N percent line coverage reach the work-list (default 100 — every
 *                file with a gap). Lower it to focus on the worst files (e.g. --threshold=60).
 * --scope=SUBSTR Keep only files whose (root-relative) path contains SUBSTR (repeatable).
 * --batch=N      Files per batch file (default 8).
 * --root=PATH    Strip this prefix from displayed paths (default: longest common dir of all files).
 *
 * Writes (in the Clover file's directory):
 *   <dir>/coverage-report.md             summary table (worst first) + batch index — read this first
 *   <dir>/coverage-batches/<NNN>.json    per-file work-lists, N files each (the Phase-4 work-lists)
 *
 * Each batch entry:
 *   { "path", "pct", "covered", "statements", "missing", "uncovered_lines",
 *     "covered_by": [ { "test": "Fqn\\Class", "suite": "Suite/Name"|null, "test_file": "tests/...php"|null } ] }
 *
 * Exit codes: 0 ok, 1 usage / no input.
 */

$threshold = 100.0;
$batchSize = 8;
$scopes = [];
$root = null;
$covXmlDir = null;
$junitFile = null;
$cloverFile = null;

foreach (\array_slice($argv, 1) as $arg) {
    if (\preg_match('/^--threshold=(\d+(?:\.\d+)?)$/', $arg, $m)) {
        $threshold = (float) $m[1];
    } elseif (\preg_match('/^--batch=(\d+)$/', $arg, $m)) {
        $batchSize = \max(1, (int) $m[1]);
    } elseif (\preg_match('/^--scope=(.+)$/', $arg, $m)) {
        $scopes[] = $m[1];
    } elseif (\preg_match('/^--root=(.+)$/', $arg, $m)) {
        $root = $m[1];
    } elseif (\preg_match('/^--coverage-xml=(.+)$/', $arg, $m)) {
        $covXmlDir = $m[1];
    } elseif (\preg_match('/^--log-junit=(.+)$/', $arg, $m)) {
        $junitFile = $m[1];
    } else {
        $cloverFile ??= $arg;
    }
}

if ($cloverFile === null || !\is_file($cloverFile)) {
    \fwrite(\STDERR, "Usage: php coverage-report.php <clover.xml> [--coverage-xml=DIR] [--log-junit=FILE] [--threshold=N] [--scope=SUBSTR] [--batch=N] [--root=PATH]\n");
    exit(1);
}

$xml = \simplexml_load_file($cloverFile);
if ($xml === false || !isset($xml->project)) {
    \fwrite(\STDERR, "error: cannot parse Clover file {$cloverFile}\n");
    exit(1);
}

$dir = \dirname($cloverFile);
$reportFile = $dir . '/coverage-report.md';
$batchDir = $dir . '/coverage-batches';

$norm = static fn(string $p): string => \str_replace('\\', '/', $p);

// --- Collect per-file stats + uncovered lines from Clover (recurse <package> too) ---
$files = [];
foreach ($xml->xpath('//file') as $file) {
    $path = $norm((string) $file['name']);
    $covered = 0;
    $statements = 0;
    $uncovered = [];
    foreach ($file->line as $line) {
        ++$statements;
        if ((int) $line['count'] > 0) {
            ++$covered;
        } else {
            $uncovered[] = (int) $line['num'];
        }
    }
    if ($statements === 0) {
        continue; // interfaces, pure-DTO files — nothing executable to cover
    }
    $files[$path] = [
        'path' => $path,
        'covered' => $covered,
        'statements' => $statements,
        'uncovered' => $uncovered,
    ];
}

if ($files === []) {
    \fwrite(\STDERR, "no files with executable statements found in {$cloverFile}\n");
    exit(1);
}

// --- Resolve the display root (strip prefix) ---
// Default to the cwd: testo is always run from the project root and writes absolute paths
// under it, so this is deterministic regardless of how many files the report holds (a
// single-file focused gate would otherwise collapse the common-prefix onto that file).
if ($root === null) {
    $paths = \array_keys($files);
    $cwd = $norm((string) \getcwd());
    if ($cwd !== '' && \array_filter($paths, static fn(string $p): bool => \str_starts_with($p, $cwd . '/')) !== []) {
        $root = $cwd;
    } else {
        // Fall back to the longest common directory prefix, cut at a slash boundary.
        $root = $paths[0];
        foreach ($paths as $p) {
            while ($root !== '' && !\str_starts_with($p, $root)) {
                $slash = \strrpos(\rtrim($root, '/'), '/');
                $root = $slash === false ? '' : \substr($root, 0, $slash + 1);
            }
            if ($root === '') {
                break;
            }
        }
        $slash = \strrpos(\rtrim($root, '/'), '/');
        $root = $slash === false ? '' : \substr($root, 0, $slash);
    }
} else {
    $root = $norm($root);
}
$root = \rtrim($root, '/');
$rel = static function (string $p) use ($root, $norm): string {
    $p = $norm($p);
    return $root !== '' && \str_starts_with($p, $root . '/') ? \substr($p, \strlen($root) + 1) : $p;
};

// --- JUnit: test class FQN -> { file, suite } ---
$testMeta = [];   // 'Fqn\\Class' => ['file' => 'tests/...php'|null, 'suite' => 'Suite/Name'|null]
if ($junitFile !== null && \is_file($junitFile)) {
    $ju = \simplexml_load_file($junitFile);
    if ($ju !== false) {
        // Walk testsuite tree; a class-level <testsuite> carries @file, its parent @name is the suite.
        $walk = static function (\SimpleXMLElement $node, ?string $suite) use (&$walk, &$testMeta, $rel): void {
            foreach ($node->testsuite as $ts) {
                $name = (string) $ts['name'];
                if (isset($ts['file'])) {
                    $testMeta[$name] = ['file' => $rel((string) $ts['file']), 'suite' => $suite];
                } else {
                    $walk($ts, $name); // a grouping suite: its name is the suite for descendants
                }
            }
        };
        $walk($ju, null);
    }
}

// --- coverage-xml: source file rel-path -> covering test classes ranked by lines covered ---
// A test class that *targets* a file covers many of its lines; one that touches it incidentally
// (shared util / global state hit by every test) covers a line or two. Counting distinct lines
// per class lets us rank the focused tests first and trim the incidental long tail.
$coveredBy = [];   // 'core/...php' => ['Fqn\\ClassA' => <lines covered>, ...] sorted desc
if ($covXmlDir !== null && \is_dir($covXmlDir)) {
    $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($covXmlDir, \FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $entry) {
        if (!$entry->isFile() || !\str_ends_with($entry->getFilename(), '.php.xml')) {
            continue;
        }
        $raw = (string) \file_get_contents($entry->getPathname());
        if (\preg_match('/<file\b[^>]*\bname="([^"]+)"[^>]*\bpath="([^"]*)"/', $raw, $fm)) {
            [$fileName, $filePath] = [$fm[1], $fm[2]];
        } elseif (\preg_match('/<file\b[^>]*\bpath="([^"]*)"[^>]*\bname="([^"]+)"/', $raw, $fm)) {
            [$fileName, $filePath] = [$fm[2], $fm[1]];
        } else {
            continue;
        }
        $relPath = $norm(\trim($filePath, '/') === '' ? $fileName : $filePath . '/' . $fileName);

        $linesByClass = [];   // class => distinct covered lines
        if (\preg_match_all('/<line nr="\d+">(.*?)<\/line>/s', $raw, $blocks)) {
            foreach ($blocks[1] as $block) {
                if (!\preg_match_all('/<covered by="([^":]+)::/', $block, $cm)) {
                    continue;
                }
                foreach (\array_unique($cm[1]) as $cls) {
                    $linesByClass[$cls] = ($linesByClass[$cls] ?? 0) + 1;
                }
            }
        }
        if ($linesByClass !== []) {
            \arsort($linesByClass); // most lines first; ties keep insertion order
            $coveredBy[$relPath] = $linesByClass;
        }
    }
}

// --- Compress a sorted int list into "40-42,51,60-65" ---
$rangeString = static function (array $nums): string {
    \sort($nums);
    $ranges = [];
    $start = $prev = null;
    foreach ($nums as $n) {
        if ($start === null) {
            $start = $prev = $n;
        } elseif ($n === $prev + 1) {
            $prev = $n;
        } else {
            $ranges[] = $start === $prev ? (string) $start : "{$start}-{$prev}";
            $start = $prev = $n;
        }
    }
    if ($start !== null) {
        $ranges[] = $start === $prev ? (string) $start : "{$start}-{$prev}";
    }
    return \implode(',', $ranges);
};

// How many covering test classes to keep per file. Incidental coverage (shared utils hit by
// every test) produces a long tail of 1-line touches that is noise, not a pointer to extend.
const COVERED_BY_CAP = 8;

// Build the covered_by detail for a file: focused tests first, capped, with an overflow marker.
$coveredByFor = static function (string $relPath) use ($coveredBy, $testMeta): array {
    $ranked = $coveredBy[$relPath] ?? [];
    $out = [];
    $kept = 0;
    foreach ($ranked as $cls => $lines) {
        if ($kept >= COVERED_BY_CAP) {
            break;
        }
        $meta = $testMeta[$cls] ?? ['file' => null, 'suite' => null];
        $out[] = ['test' => $cls, 'suite' => $meta['suite'], 'test_file' => $meta['file'], 'lines' => $lines];
        ++$kept;
    }
    $more = \count($ranked) - $kept;
    if ($more > 0) {
        $out[] = ['more' => $more]; // marker: this many further incidental tests omitted
    }
    return $out;
};

// --- Project totals over ALL files (before scope/threshold filtering) ---
$projStmt = $projCov = 0;
foreach ($files as $f) {
    $projStmt += $f['statements'];
    $projCov += $f['covered'];
}
$projPct = $projStmt > 0 ? \round(100 * $projCov / $projStmt, 1) : 0.0;

// --- Apply scope + threshold filters to build the work-list ---
$worklist = [];
foreach ($files as $f) {
    $relPath = $rel($f['path']);
    if ($scopes !== []) {
        $hit = false;
        foreach ($scopes as $s) {
            if (\str_contains($relPath, $norm($s))) {
                $hit = true;
                break;
            }
        }
        if (!$hit) {
            continue;
        }
    }
    $missing = $f['statements'] - $f['covered'];
    $pct = \round(100 * $f['covered'] / $f['statements'], 1);
    if ($missing === 0 || $pct >= $threshold) {
        continue;
    }
    $worklist[] = [
        'path' => $relPath,
        'pct' => $pct,
        'covered' => $f['covered'],
        'statements' => $f['statements'],
        'missing' => $missing,
        'uncovered_lines' => $rangeString($f['uncovered']),
        'covered_by' => $coveredByFor($relPath),
    ];
}

// Worst impact first: most uncovered statements at the top.
\usort($worklist, static fn(array $a, array $b): int => $b['missing'] <=> $a['missing'] ?: ($a['pct'] <=> $b['pct']));

// --- Summary report ---
$enrich = ($covXmlDir !== null || $junitFile !== null);
$out = [
    '# Coverage report',
    '',
    \sprintf(
        'Project total: **%.1f%%** line coverage (%d / %d statements covered across %d files).',
        $projPct,
        $projCov,
        $projStmt,
        \count($files),
    ),
    '',
];

$scopeLabel = $scopes === [] ? 'whole project' : 'scope ' . \implode(' + ', $scopes);
$out[] = \sprintf(
    'Work-list: %d file(s) below %.0f%% (%s), sorted by uncovered statements (most first).',
    \count($worklist),
    $threshold,
    $scopeLabel,
);
$out[] = '';

if ($worklist === []) {
    $out[] = 'Nothing under the threshold. 🎉';
} else {
    $header = $enrich
        ? '| File | Coverage | Covered/Stmt | Missing | Existing tests |'
        : '| File | Coverage | Covered/Stmt | Missing |';
    $sep = $enrich ? '|---|--:|--:|--:|---|' : '|---|--:|--:|--:|';
    $out[] = $header;
    $out[] = $sep;
    foreach ($worklist as $w) {
        if ($enrich) {
            // Show the top focused tests (short name + suite + lines covered); summarise the tail.
            if ($w['covered_by'] === []) {
                $tests = '_none_';
            } else {
                $labels = [];
                $tail = 0;
                foreach ($w['covered_by'] as $cb) {
                    if (isset($cb['more'])) {
                        $tail += (int) $cb['more'];
                        continue;
                    }
                    if (\count($labels) >= 4) {
                        ++$tail;
                        continue;
                    }
                    $short = (string) \substr(\strrchr($cb['test'], '\\') ?: $cb['test'], 1) ?: $cb['test'];
                    $suite = $cb['suite'] !== null ? " _({$cb['suite']})_" : '';
                    $labels[] = "{$short}{$suite} · {$cb['lines']}l";
                }
                if ($tail > 0) {
                    $labels[] = "+{$tail} more";
                }
                $tests = \implode('<br>', $labels);
            }
            $out[] = \sprintf(
                '| `%s` | %.1f%% | %d/%d | %d | %s |',
                $w['path'],
                $w['pct'],
                $w['covered'],
                $w['statements'],
                $w['missing'],
                $tests,
            );
        } else {
            $out[] = \sprintf(
                '| `%s` | %.1f%% | %d/%d | %d |',
                $w['path'],
                $w['pct'],
                $w['covered'],
                $w['statements'],
                $w['missing'],
            );
        }
    }
}
$out[] = '';

// --- Split the work-list into batch files ---
if (!\is_dir($batchDir)) {
    \mkdir($batchDir, 0777, true);
}
foreach (\glob($batchDir . '/*.json') ?: [] as $stale) {
    \unlink($stale);
}

$batchIndex = [];
$stdoutLines = [];
foreach (\array_chunk($worklist, $batchSize) as $n => $chunk) {
    $name = \sprintf('%03d.json', $n + 1);
    \file_put_contents(
        $batchDir . '/' . $name,
        \json_encode($chunk, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) . "\n",
    );
    $relFile = 'coverage-batches/' . $name;
    $missingSum = \array_sum(\array_column($chunk, 'missing'));
    $batchIndex[] = \sprintf('| `%s` | %d | %d |', $relFile, \count($chunk), $missingSum);
    $stdoutLines[] = \sprintf('  %s/%s (%d files, %d uncovered stmts)', $batchDir, $name, \count($chunk), $missingSum);
}

$out[] = '## Work-list — batches';
$out[] = '';
if ($batchIndex === []) {
    $out[] = 'No batches — nothing to cover. 🎉';
} else {
    $out[] = \sprintf(
        '%d file(s) split into %d batch file(s) of up to %d. In Phase 4 process **one batch at a time**, **one file per subagent**.',
        \count($worklist),
        \count($batchIndex),
        $batchSize,
    );
    if (!$enrich) {
        $out[] = '';
        $out[] = '> Tip: re-run with `--coverage-xml=<dir>` and `--log-junit=<file>` to annotate each file with the tests that already cover it.';
    }
    $out[] = '';
    $out[] = '| Batch file | Files | Uncovered stmts |';
    $out[] = '|---|--:|--:|';
    foreach ($batchIndex as $row) {
        $out[] = $row;
    }
}
$out[] = '';

\file_put_contents($reportFile, \implode("\n", $out) . "\n");

// --- Console summary ---
\fwrite(\STDOUT, \sprintf("wrote %s (project %.1f%%, %d files in work-list%s)\n", $reportFile, $projPct, \count($worklist), $enrich ? ', test-annotated' : ''));
if ($batchIndex === []) {
    \fwrite(\STDOUT, "no files under the threshold — nothing to batch\n");
} else {
    \fwrite(\STDOUT, \sprintf("%d batch file(s) in %s:\n", \count($batchIndex), $batchDir));
    foreach ($stdoutLines as $line) {
        \fwrite(\STDOUT, $line . "\n");
    }
}
