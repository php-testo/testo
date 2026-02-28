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
        if ($result->lines === []) {
            return '';
        }

        $iters = \count($result->cases[0]->iterations);
        $showRstdev = $iters > 1;

        $callsByName = [];
        foreach ($result->cases as $case) {
            $callsByName[$case->name] = $case->iterations[0]->calls;
        }

        $headers = ['#', 'Name', 'Iters', 'Calls', 'Avg', 'Med', 'Avg*', 'RStDev*', 'Rejected'];
        $rows = [];
        foreach ($result->lines as $line) {
            $rows[] = [
                (string) $line->place,
                $line->name,
                (string) $iters,
                (string) ($callsByName[$line->name] ?? 0),
                self::formatTime($line->mean->value, $line->mean->diff),
                self::formatTime($line->med->value, $line->med->diff),
                self::formatTime($line->avg->value, $line->avg->diff),
                $showRstdev ? self::formatRstdev($line->rstdev) : '',
                $line->rejected > 0 ? (string) $line->rejected : '',
            ];
        }

        return self::renderTable($headers, $rows);
    }

    public static function rounds(BenchResult $result): string
    {
        if ($result->cases === []) {
            return '';
        }

        $headers = ['Name', 'Iter', 'Calls', 'Time', 'Time avg', 'Memory'];

        $dataGroups = [];
        foreach ($result->cases as $case) {
            $rows = [];
            foreach ($case->iterations as $ii => $snap) {
                $rows[] = [
                    $ii === 0 ? $case->name : '',
                    (string) ($ii + 1),
                    (string) $snap->calls,
                    self::formatTime($snap->time),
                    self::formatTime($snap->time / $snap->calls),
                    self::formatMemory((float) $snap->memory),
                ];
            }
            $dataGroups[] = $rows;
        }

        $allRows = \array_merge(...$dataGroups);
        $widths = self::calculateWidths($headers, $allRows);

        $separator = self::separator($widths);
        $lines = [$separator, self::row($headers, $widths), $separator];

        foreach ($dataGroups as $rows) {
            foreach ($rows as $row) {
                $lines[] = self::row($row, $widths);
            }
            $lines[] = $separator;
        }

        return \implode("\n", $lines);
    }

    private static function formatMemory(float $bytes): string
    {
        if ($bytes === 0.0) {
            return '0';
        }

        return match (true) {
            $bytes >= 1024 ** 3 => \sprintf('%.2f GB', $bytes / 1024 ** 3),
            $bytes >= 1024 ** 2 => \sprintf('%.2f MB', $bytes / 1024 ** 2),
            $bytes >= 1024 => \sprintf('%.2f KB', $bytes / 1024),
            default => \sprintf('%.0f B', $bytes),
        };
    }

    private static function formatTime(float $us, float $diff = 0.0): string
    {
        if ($us === 0.0) {
            return '0';
        }

        $formatted = match (true) {
            $us >= 1_000_000.0 => \sprintf('%.3fs', $us / 1_000_000),
            $us >= 1_000.0 => \sprintf('%.3fms', $us / 1_000),
            $us >= 1.0 => \sprintf('%.3fµs', $us),
            default => \sprintf('%.3fns', $us * 1_000),
        };

        return $diff === 0.0 ? $formatted : \sprintf('%s (%+.1f%%)', $formatted, $diff);
    }

    private static function formatRstdev(float $rstdev): string
    {
        return \sprintf('±%.2f%%', $rstdev);
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
}
