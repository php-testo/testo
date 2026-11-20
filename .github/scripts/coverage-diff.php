<?php

/**
 * Renders a Markdown comparison of Clover reports produced by different coverage drivers:
 * a summary table and, per file, every source line the drivers disagree on.
 *
 * Usage: php coverage-diff.php <source-root> <label>=<clover.xml> [<label>=<clover.xml> ...]
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
\count($reports) < 2 and throw new \InvalidArgumentException('At least two reports are required.');

$labels = \array_keys($reports);
$files = \array_unique(\array_merge(...\array_map(\array_keys(...), \array_values($reports))));
\sort($files);

$sections = [];
$diffLines = 0;
foreach ($files as $file) {
    $lines = [];
    foreach ($reports as $report) {
        $lines += $report[$file] ?? [];
    }
    $lines = \array_keys($lines);
    \sort($lines);

    $source = @\file($file,\FILE_IGNORE_NEW_LINES) ?: [];
    $rows = [];
    foreach ($lines as $num) {
        $states = \array_map(static fn(array $report): ?bool => $report[$file][$num] ?? null, $reports);
        if (\count(\array_unique(\array_map(static fn(?bool $s): string => \var_export($s, true), $states))) === 1) {
            continue;
        }

        $code = \trim($source[$num - 1] ?? '');
        \mb_strlen($code) > 100 and $code = \mb_substr($code, 0, 100) . '…';
        $rows[] = \sprintf(
            '| %d | %s | <code>%s</code> |',
            $num,
            \implode(' | ', \array_map(mark(...), $states)),
            \str_replace('|', '&#124;', \htmlspecialchars($code)),
        );
    }

    if ($rows === []) {
        continue;
    }

    $diffLines += \count($rows);
    $missing = \array_keys(\array_filter($reports, static fn(array $report): bool => !isset($report[$file])));
    $sections[] = \implode("\n", [
        \sprintf(
            '<details><summary><code>%s</code>: %d line(s)%s</summary>',
            $file,
            \count($rows),
            $missing === [] ? '' : ', absent in ' . \implode(', ', $missing),
        ),
        '',
        '| Line | ' . \implode(' | ', $labels) . ' | Code |',
        '|---:|' . \str_repeat(':---:|', \count($labels)) . '---|',
        ...$rows,
        '',
        '</details>',
    ]);
}

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
$head = [
    MARKER,
    '## Coverage driver diff',
    '',
    \trim(($sha ? 'Commit ' . \substr($sha, 0, 8) . '. ' : '') . ($run ? "[Run]($run)." : '')),
    '',
    ...$summary,
    '',
    \sprintf('%d line(s) in %d file(s) differ. ✅ covered, ❌ executable but not covered, ➖ not executable.', $diffLines, \count($sections)),
    '',
];

$body = \implode("\n", $head);
$shown = 0;
foreach ($sections as $section) {
    if (\strlen($body) + \strlen($section) > MAX_LENGTH) {
        $body .= \sprintf("\n_%d more file(s) omitted: comment size limit._\n", \count($sections) - $shown);
        break;
    }
    $body .= $section . "\n";
    ++$shown;
}

echo $body;

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
