<?php

/**
 * Builds the PR comment comparing turbocov with a baseline coverage driver.
 *
 *   section <source-root> <key> <base-label>=<clover.xml> <subject-label>=<clover.xml> <t-none> <t-base> <t-subject>
 *     Renders one baseline's section: coverage totals and every source line the two reports disagree on.
 *     Timings are seconds of the same suite run without coverage, with the baseline and with the subject.
 *
 *   merge <existing-comment.md> <section.md>
 *     Replaces the section with the same key in the existing comment (an empty file starts a new one)
 *     and rebuilds the timing table from all sections, so each job owns only its own part.
 *
 * Clover file paths are made relative by cutting <source-root>; source lines are read relative to
 * the working directory.
 */

declare(strict_types=1);

const MARKER = '<!-- coverage-driver-diff -->';
const SECTION_ORDER = ['xdebug', 'pcov'];
// GitHub rejects comment bodies over 65536 characters; each section gets an equal share.
const SECTION_BUDGET = 30000;

match ($argv[1] ?? null) {
    'section' => section(...\array_slice($argv, 2)),
    'merge' => merge(...\array_slice($argv, 2)),
    default => throw new \InvalidArgumentException('Usage: coverage-diff.php section|merge ...'),
};

function section(
    string $root,
    string $key,
    string $base,
    string $subject,
    string $timeNone,
    string $timeBase,
    string $timeSubject,
): void {
    $root = \rtrim($root, '/') . '/';
    [$baseLabel, $baseReport] = parseReportArg($base, $root);
    [$subjectLabel, $subjectReport] = parseReportArg($subject, $root);

    $meta = [
        'label' => $baseLabel,
        'subject' => $subjectLabel,
        'none' => (float) $timeNone,
        'base' => (float) $timeBase,
        'turbo' => (float) $timeSubject,
    ];

    $run = \getenv('RUN_URL');
    $sha = \getenv('GITHUB_SHA');

    echo \implode("\n", [
        \sprintf('<!-- section:%s %s -->', $key, \json_encode($meta, \JSON_UNESCAPED_UNICODE)),
        "### $subjectLabel vs $baseLabel",
        '',
        \trim(($sha ? 'Commit ' . \substr($sha, 0, 8) . '. ' : '') . ($run ? "[Run]($run)." : '')),
        '',
        '| Driver | Files | Statements | Covered |',
        '|---|---:|---:|---:|',
        totalsRow($baseLabel, $baseReport),
        totalsRow($subjectLabel, $subjectReport),
        '',
        renderDiff($baseLabel, $baseReport, $subjectLabel, $subjectReport),
        "<!-- /section:$key -->",
    ]), "\n";
}

function merge(string $existingPath, string $sectionPath): void
{
    $sections = parseSections((string) @\file_get_contents($existingPath));
    $sections = \array_replace($sections, parseSections((string) \file_get_contents($sectionPath)));

    \uksort($sections, static function (string $a, string $b): int {
        $ia = \array_search($a, SECTION_ORDER, true);
        $ib = \array_search($b, SECTION_ORDER, true);
        return [$ia === false, $ia, $a] <=> [$ib === false, $ib, $b];
    });

    $subject = \current($sections)['meta']['subject'] ?? 'turbocov';
    $table = [
        "| Baseline | No coverage | Baseline driver | $subject |",
        '|---|---:|---:|---:|',
    ];
    foreach ($sections as $section) {
        $meta = $section['meta'];
        $table[] = \sprintf(
            '| %s | %.1f s | %.1f s | %.1f s |',
            $meta['label'],
            $meta['none'],
            $meta['base'],
            $meta['turbo'],
        );
    }

    echo \implode("\n", [
        MARKER,
        '## Coverage driver diff',
        '',
        'Each row is one job: the same suite (`--type=!bench`) run without coverage, with the baseline driver and with ' . $subject . ', one after another on the same runner.',
        '',
        ...$table,
        '',
        '✅ covered, ❌ executable but not covered, ➖ not executable.',
        '',
        ...\array_column($sections, 'text'),
    ]), "\n";
}

/**
 * @return array<string, array{meta: array<string, mixed>, text: string}>
 */
function parseSections(string $body): array
{
    \preg_match_all('~<!-- section:(\w+) (\{.*?\}) -->.*?<!-- /section:\1 -->~s', $body, $matches, \PREG_SET_ORDER);

    $sections = [];
    foreach ($matches as [$text, $key, $meta]) {
        $sections[$key] = ['meta' => \json_decode($meta, true, flags: \JSON_THROW_ON_ERROR), 'text' => $text . "\n"];
    }

    return $sections;
}

/**
 * @param array<string, array<int, bool>> $base
 * @param array<string, array<int, bool>> $subject
 */
function renderDiff(string $baseLabel, array $base, string $subjectLabel, array $subject): string
{
    $files = \array_unique([...\array_keys($base), ...\array_keys($subject)]);
    \sort($files);

    $blocks = [];
    $lineCount = 0;
    foreach ($files as $file) {
        $lines = \array_keys(($base[$file] ?? []) + ($subject[$file] ?? []));
        \sort($lines);

        $source = @\file($file, \FILE_IGNORE_NEW_LINES) ?: [];
        $rows = [];
        foreach ($lines as $num) {
            $a = $base[$file][$num] ?? null;
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
            !isset($base[$file]) => " (absent in $baseLabel)",
            !isset($subject[$file]) => " (absent in $subjectLabel)",
            default => '',
        };
        $blocks[] = \implode("\n", [
            "**`$file`**$absent",
            '',
            "| Line | $baseLabel | $subjectLabel | Code |",
            '|---:|:---:|:---:|---|',
            ...$rows,
        ]);
    }

    if ($blocks === []) {
        return "_No line differences._\n";
    }

    $content = '';
    foreach ($blocks as $i => $block) {
        if (\strlen($content) + \strlen($block) > SECTION_BUDGET) {
            $content .= \sprintf("_%d more file(s) omitted: comment size limit._\n\n", \count($blocks) - $i);
            break;
        }
        $content .= $block . "\n\n";
    }

    return \implode("\n", [
        \sprintf('<details><summary>%d line(s) in %d file(s) differ</summary>', $lineCount, \count($blocks)),
        '',
        \rtrim($content),
        '',
        '</details>',
        '',
    ]);
}

/**
 * @param array<string, array<int, bool>> $report
 */
function totalsRow(string $label, array $report): string
{
    $statements = \array_sum(\array_map(\count(...), $report));
    $covered = \array_sum(\array_map(static fn(array $lines): int => \count(\array_filter($lines)), $report));

    return \sprintf(
        '| %s | %d | %d | %d (%.2f %%) |',
        $label,
        \count($report),
        $statements,
        $covered,
        $statements > 0 ? $covered / $statements * 100 : 0,
    );
}

/**
 * @return array{string, array<string, array<int, bool>>}
 */
function parseReportArg(string $arg, string $root): array
{
    [$label, $path] = \explode('=', $arg, 2) + [1 => null];
    $path === null and throw new \InvalidArgumentException("Expected <label>=<clover.xml>, got `$arg`.");

    return [$label, loadClover($path, $root)];
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
