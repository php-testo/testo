<?php

/**
 * Renders a Markdown comparison of one coverage driver's Clover report against others:
 * a summary table and, per baseline, one collapsible section with every source line the two disagree on.
 *
 * Usage: php coverage-diff.php <source-root> <subject>=<clover.xml> <baseline>=<clover.xml> [...]
 * Clover file paths are made relative by cutting <source-root>, so all reports must come from checkouts
 * at the same path. Source lines are read relative to the working directory.
 */

declare(strict_types=1);

const MARKER = '<!-- coverage-driver-diff -->';
// GitHub rejects comment bodies over 65536 characters.
const MAX_LENGTH = 60000;

$root = \rtrim($argv[1] ?? '', '/') . '/';
$reports = [];
foreach (\array_slice($argv, 2) as $arg) {
    [$label, $path] = \explode('=', $arg, 2) + [1 => null];
    $path === null and throw new \InvalidArgumentException("Expected <label>=<clover.xml>, got `$arg`.");
    $reports[$label] = loadClover($path, $root);
}
\count($reports) < 2 and throw new \InvalidArgumentException('A subject and at least one baseline are required.');

$subject = \array_key_first($reports);

$summary = [
    '| Driver | Files | Statements | Covered |',
    '|---|---:|---:|---:|',
];
foreach ($reports as $label => $report) {
    $statements = \array_sum(\array_map(\count(...), $report));
    $covered = \array_sum(\array_map(static fn(array $lines): int => \count(\array_filter($lines)), $report));
    $summary[] = \sprintf(
        '| %s | %d | %d | %d (%.2f %%) |',
        $label,
        \count($report),
        $statements,
        $covered,
        $statements > 0 ? $covered / $statements * 100 : 0,
    );
}

$run = \getenv('RUN_URL');
$sha = \getenv('GITHUB_SHA');
$body = \implode("\n", [
    MARKER,
    '## Coverage driver diff',
    '',
    \trim(($sha ? 'Commit ' . \substr($sha, 0, 8) . '. ' : '') . ($run ? "[Run]($run)." : '')),
    '',
    ...$summary,
    '',
    '✅ covered, ❌ executable but not covered, ➖ not executable.',
    '',
    '',
]);

$baselines = \array_slice($reports, 1, preserve_keys: true);
$budget = \intdiv(MAX_LENGTH - \strlen($body), \count($baselines));
foreach ($baselines as $label => $baseline) {
    $body .= renderSection($label, $baseline, $subject, $reports[$subject], $budget) . "\n\n";
}

echo $body;

/**
 * @param array<string, array<int, bool>> $baseline
 * @param array<string, array<int, bool>> $subject
 * @param int $budget Maximum section length in bytes; files past it are counted but not listed.
 */
function renderSection(string $baselineLabel, array $baseline, string $subjectLabel, array $subject, int $budget): string
{
    $files = \array_unique([...\array_keys($baseline), ...\array_keys($subject)]);
    \sort($files);

    $blocks = [];
    $lineCount = 0;
    foreach ($files as $file) {
        $lines = \array_keys(($baseline[$file] ?? []) + ($subject[$file] ?? []));
        \sort($lines);

        $source = @\file($file, \FILE_IGNORE_NEW_LINES) ?: [];
        $rows = [];
        foreach ($lines as $num) {
            $a = $baseline[$file][$num] ?? null;
            $b = $subject[$file][$num] ?? null;
            if ($a === $b) {
                continue;
            }

            $code = \trim($source[$num - 1] ?? '');
            \mb_strlen($code) > 100 and $code = \mb_substr($code, 0, 100) . '…';
            $rows[] = \sprintf(
                '| %d | %s | %s | <code>%s</code> |',
                $num,
                mark($a),
                mark($b),
                \str_replace('|', '&#124;', \htmlspecialchars($code)),
            );
        }

        if ($rows === []) {
            continue;
        }

        $lineCount += \count($rows);
        $absent = match (true) {
            !isset($baseline[$file]) => " (absent in $baselineLabel)",
            !isset($subject[$file]) => " (absent in $subjectLabel)",
            default => '',
        };
        $blocks[] = \implode("\n", [
            "**`$file`**$absent",
            '',
            "| Line | $baselineLabel | $subjectLabel | Code |",
            '|---:|:---:|:---:|---|',
            ...$rows,
        ]);
    }

    $content = '';
    foreach ($blocks as $i => $block) {
        if (\strlen($content) + \strlen($block) > $budget) {
            $content .= \sprintf("_%d more file(s) omitted: comment size limit._\n\n", \count($blocks) - $i);
            break;
        }
        $content .= $block . "\n\n";
    }

    return \implode("\n", [
        \sprintf(
            '<details><summary><b>%s vs %s</b>: %d line(s) in %d file(s)</summary>',
            $subjectLabel,
            $baselineLabel,
            $lineCount,
            \count($blocks),
        ),
        '',
        $blocks === [] ? '_No differences._' : \rtrim($content),
        '',
        '</details>',
    ]);
}

/**
 * @return array<string, array<int, bool>> relative file => line => covered, statement lines only
 */
function loadClover(string $path, string $root): array
{
    $xml = \simplexml_load_file($path);
    $xml === false and throw new \RuntimeException("Cannot parse `$path`.");

    $result = [];
    foreach ($xml->xpath('//file') as $file) {
        $name = (string) $file['name'];
        \str_starts_with($name, $root) and $name = \substr($name, \strlen($root));
        foreach ($file->line as $line) {
            (string) $line['type'] === 'stmt' and $result[$name][(int) $line['num']] = (int) $line['count'] > 0;
        }
    }

    return $result;
}

function mark(?bool $state): string
{
    return match ($state) {
        true => '✅',
        false => '❌',
        null => '➖',
    };
}
