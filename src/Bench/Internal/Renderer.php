<?php

declare(strict_types=1);

namespace Testo\Bench\Internal;

use Testo\Bench\Dto\BenchResult;

/**
 * Renders benchmark results as an ASCII table.
 *
 * @internal
 */
final class Renderer
{
    public static function table(BenchResult $result): string
    {
        if ($result->explanation === []) {
            return '';
        }

        $iters = \count($result->iterations);
        $showRstdev = $iters > 1;

        $revsByName = [];
        foreach ($result->aliases as $idx => $alias) {
            $revsByName[(string) $alias] = $result->iterations[0]->cases[$idx]->revolutions;
        }

        $headers = ['#', 'Name', 'Iters', 'Revs', 'Memory', 'Avg Time', 'RStDev'];
        $rows = [];
        foreach ($result->explanation as $line) {
            $rows[] = [
                (string) $line->place,
                $line->name,
                (string) $iters,
                (string) ($revsByName[$line->name] ?? 0),
                self::formatMemory($line->memory->value, $line->memory->diff),
                self::formatTime($line->time->value, $line->time->diff),
                $showRstdev ? self::formatRstdev($line->rstdev) : '',
            ];
        }

        return self::renderTable($headers, $rows);
    }

    public static function rounds(BenchResult $result): string
    {
        if ($result->iterations === []) {
            return '';
        }

        $subHeaders = ['Name', 'Round', 'Revs', 'Min', 'Avg', 'Max', 'Total', 'Min', 'Avg', 'Max', 'Total'];
        $caseCount = \count($result->aliases);

        $dataGroups = [];
        for ($c = 0; $c < $caseCount; ++$c) {
            $rows = [];
            foreach ($result->iterations as $ri => $round) {
                $snap = $round->cases[$c];
                $rows[] = [
                    $ri === 0 ? (string) $result->aliases[$c] : '',
                    (string) $round->iteration,
                    (string) $snap->revolutions,
                    self::formatTime($snap->time->min, 0.0),
                    self::formatTime($snap->time->avg, 0.0),
                    self::formatTime($snap->time->max, 0.0),
                    self::formatTime($snap->time->total, 0.0),
                    self::formatMemory($snap->memory->min, 0.0),
                    self::formatMemory($snap->memory->avg, 0.0),
                    self::formatMemory($snap->memory->max, 0.0),
                    self::formatMemory($snap->memory->total, 0.0),
                ];
            }
            $dataGroups[] = $rows;
        }

        $allRows = \array_merge(...$dataGroups);
        $widths = self::calculateWidths($subHeaders, $allRows);

        $lines = [];
        $lines[] = self::mergedSeparator($widths, [[3, 6], [7, 10]]);
        $lines[] = self::mergedHeaderRow($widths, [[0, 0, ''], [1, 1, ''], [2, 2, ''], [3, 6, 'Time'], [7, 10, 'Memory']]);
        $lines[] = self::centeredRow($subHeaders, $widths);
        $lines[] = self::separator($widths);

        foreach ($dataGroups as $rows) {
            foreach ($rows as $row) {
                $lines[] = self::row($row, $widths);
            }
            $lines[] = self::separator($widths);
        }

        return \implode("\n", $lines);
    }

    private static function formatMemory(float $bytes, float $diff): string
    {
        if ($bytes === 0.0) {
            return '0';
        }

        $formatted = match (true) {
            $bytes >= 1024 ** 3 => \sprintf('%.2f GB', $bytes / 1024 ** 3),
            $bytes >= 1024 ** 2 => \sprintf('%.2f MB', $bytes / 1024 ** 2),
            $bytes >= 1024 => \sprintf('%.2f KB', $bytes / 1024),
            default => \sprintf('%.0f B', $bytes),
        };

        return $diff === 0.0 ? $formatted : \sprintf('%s (%+.1f%%)', $formatted, $diff);
    }

    private static function formatTime(float $ms, float $diff): string
    {
        if ($ms === 0.0) {
            return '0';
        }

        $formatted = match (true) {
            $ms >= 1_000.0 => \sprintf('%.3fs', $ms / 1_000),
            $ms >= 1.0 => \sprintf('%.3fms', $ms),
            $ms >= 0.001 => \sprintf('%.3fµs', $ms * 1_000),
            default => \sprintf('%.3fns', $ms * 1_000_000),
        };

        return $diff === 0.0 ? $formatted : \sprintf('%s (%+.1f%%)', $formatted, $diff);
    }

    private static function formatRstdev(float $rstdev): string
    {
        return \sprintf('±%.2f%%', $rstdev * 100);
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    private static function renderTable(array $headers, array $rows): string
    {
        $widths = self::calculateWidths($headers, $rows);

        $separator = self::separator($widths);
        $lines = [$separator, self::row($headers, $widths), $separator];

        foreach ($rows as $r) {
            $lines[] = self::row($r, $widths);
        }

        $lines[] = $separator;

        return \implode("\n", $lines);
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     * @return list<int>
     */
    private static function calculateWidths(array $headers, array $rows): array
    {
        $widths = \array_map(static fn(string $h): int => \mb_strlen($h), $headers);

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = \max($widths[$i], \mb_strlen($cell));
            }
        }

        return $widths;
    }

    /**
     * @param list<int> $widths
     */
    private static function separator(array $widths): string
    {
        $parts = \array_map(static fn(int $w): string => \str_repeat('-', $w + 2), $widths);

        return '+' . \implode('+', $parts) . '+';
    }

    /**
     * @param list<string> $cells
     * @param list<int> $widths
     */
    private static function row(array $cells, array $widths): string
    {
        $parts = [];
        foreach ($cells as $i => $cell) {
            $padding = $widths[$i] - \mb_strlen($cell);
            $parts[] = ' ' . $cell . \str_repeat(' ', $padding) . ' ';
        }

        return '|' . \implode('|', $parts) . '|';
    }

    /**
     * @param list<string> $cells
     * @param list<int> $widths
     */
    private static function centeredRow(array $cells, array $widths): string
    {
        $parts = [];
        foreach ($cells as $i => $cell) {
            $parts[] = ' ' . self::centerPad($cell, $widths[$i]) . ' ';
        }

        return '|' . \implode('|', $parts) . '|';
    }

    /**
     * Separator with certain column ranges merged (internal `+` replaced with `-`).
     *
     * @param list<int> $widths
     * @param list<array{int, int}> $merges Column ranges [from, to] to merge.
     */
    private static function mergedSeparator(array $widths, array $merges): string
    {
        $internal = [];
        foreach ($merges as [$from, $to]) {
            for ($i = $from; $i < $to; ++$i) {
                $internal[$i] = true;
            }
        }

        $result = '+';
        foreach ($widths as $i => $w) {
            $result .= \str_repeat('-', $w + 2);
            $result .= isset($internal[$i]) ? '-' : '+';
        }

        return $result;
    }

    /**
     * Header row with labels centered across merged column spans.
     *
     * @param list<int> $widths
     * @param list<array{int, int, string}> $spans Each span is [from, to, label].
     */
    private static function mergedHeaderRow(array $widths, array $spans): string
    {
        $result = '|';
        $i = 0;
        $count = \count($widths);

        while ($i < $count) {
            $handled = false;
            foreach ($spans as [$from, $to, $label]) {
                if ($i === $from) {
                    $span = self::spanWidth($widths, $from, $to);
                    $result .= ' ' . self::centerPad($label, $span - 2) . ' |';
                    $i = $to + 1;
                    $handled = true;
                    break;
                }
            }

            if (!$handled) {
                $result .= \str_repeat(' ', $widths[$i] + 2) . '|';
                ++$i;
            }
        }

        return $result;
    }

    /**
     * Content width for merged columns from $from to $to (inclusive).
     *
     * @param list<int> $widths
     */
    private static function spanWidth(array $widths, int $from, int $to): int
    {
        $sum = 0;
        for ($i = $from; $i <= $to; ++$i) {
            $sum += $widths[$i];
        }

        return $sum + 3 * ($to - $from) + 2;
    }

    private static function centerPad(string $text, int $width): string
    {
        $len = \mb_strlen($text);
        if ($len >= $width) {
            return $text;
        }

        $left = (int) (($width - $len) / 2);
        $right = $width - $len - $left;

        return \str_repeat(' ', $left) . $text . \str_repeat(' ', $right);
    }
}
