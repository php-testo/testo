<?php

declare(strict_types=1);

namespace Testo\Bench\Internal;

use Testo\Bench\Dto\BenchResult;
use Testo\Bench\Dto\Report;
use Testo\Bench\Dto\Report\Severity;

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
        $hasRejected = false;
        foreach ($result->lines as $line) {
            if ($line->rejected > 0) {
                $hasRejected = true;
                break;
            }
        }

        $callsByName = [];
        foreach ($result->cases as $case) {
            $callsByName[$case->name] = $case->iterations[0]->calls;
        }

        $headers = ['Name', 'Iters', 'Calls', 'Mean', 'Median', 'RStDev'];
        $spans = [[0, 2, 'Benchmark setup'], [3, 5, 'Time results']];
        $merges = [[0, 2], [3, 5]];

        if ($hasRejected) {
            \array_push($headers, 'Rej.', 'Mean*', 'RStDev*');
            $spans[] = [6, 8, 'Filtered results'];
            $merges[] = [6, 8];
        }

        $hasWarnings = false;
        $hasDangers = false;
        foreach ($result->lines as $line) {
            foreach ($line->reports as $report) {
                match ($report->severity) {
                    Severity::Warning => $hasWarnings = true,
                    Severity::Danger => $hasDangers = true,
                    default => null,
                };
            }
        }

        $summaryStart = \count($headers);
        $headers[] = 'Place';
        $hasWarnings and $headers[] = 'Warnings';
        $hasDangers and $headers[] = 'Dangers';
        $summaryEnd = \count($headers) - 1;
        $spans[] = [$summaryStart, $summaryEnd, 'Summary'];
        $summaryStart !== $summaryEnd and $merges[] = [$summaryStart, $summaryEnd];

        $rows = [];
        foreach ($result->lines as $line) {
            $row = [
                $line->name,
                (string) $iters,
                (string) ($callsByName[$line->name] ?? 0),
                self::formatTime($line->avg->value, $line->avg->diff),
                self::formatTime($line->med->value, $line->med->diff),
                $iters > 1 ? self::formatRstdev($line->rstdev) : '',
            ];

            if ($hasRejected) {
                $row[] = $line->rejected > 0 ? (string) $line->rejected : '';
                $row[] = self::formatTime($line->favg->value, $line->favg->diff);
                $row[] = $iters > 1 ? self::formatRstdev($line->frstdev) : '';
            }

            $row[] = self::ordinal($line->place);
            $hasWarnings and $row[] = self::joinReports($line->reports, Severity::Warning);
            $hasDangers and $row[] = self::joinReports($line->reports, Severity::Danger);

            $rows[] = $row;
        }

        $rightAlign = [5 => true];
        if ($hasRejected) {
            $rightAlign[8] = true;
        }

        $widths = self::calculateWidths($headers, $rows);
        $widths = self::adjustWidthsForSpans($widths, $spans);

        $lines = [];
        $lines[] = self::mergedSeparator($widths, $merges);
        $lines[] = self::mergedHeaderRow($widths, $spans);
        $lines[] = self::row($headers, $widths);
        $lines[] = self::separator($widths);

        foreach ($rows as $r) {
            $lines[] = self::row($r, $widths, $rightAlign);
        }

        $lines[] = self::separator($widths);

        return \implode("\n", $lines);
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

    public static function recommendations(BenchResult $result): string
    {
        $dangers = [];
        $warnings = [];
        $notices = [];

        foreach ($result->lines as $line) {
            foreach ($line->reports as $report) {
                match ($report->severity) {
                    Severity::Danger => $dangers[$report->reason] = $report->advice,
                    Severity::Warning => $warnings[$report->reason] = $report->advice,
                    Severity::Notice => $notices[$report->reason] = $report->advice,
                    default => null,
                };
            }
        }

        if ($dangers === [] && $warnings === [] && $notices === []) {
            return '';
        }

        $lines = ['', 'Recommendations:'];

        foreach ($dangers as $reason => $advice) {
            $lines[] = "  ✗ {$reason}: {$advice}";
        }

        foreach ($warnings as $reason => $advice) {
            $lines[] = "  ⚠ {$reason}: {$advice}";
        }

        foreach ($notices as $reason => $advice) {
            $lines[] = "  ℹ {$reason}: {$advice}";
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
            $us >= 1_000_000.0 => \sprintf('%.2fs', $us / 1_000_000),
            $us >= 1_000.0 => \sprintf('%.2fms', $us / 1_000),
            $us >= 1.0 => \sprintf('%.2fµs', $us),
            default => \sprintf('%.2fns', $us * 1_000),
        };

        return $diff === 0.0 ? $formatted : \sprintf('%s (%+.1f%%)', $formatted, $diff);
    }

    private static function formatRstdev(float $rstdev): string
    {
        return \sprintf('±%.2f%%', $rstdev);
    }

    private static function ordinal(int $n): string
    {
        $suffix = match (true) {
            $n % 100 >= 11 && $n % 100 <= 13 => 'th',
            $n % 10 === 1 => 'st',
            $n % 10 === 2 => 'nd',
            $n % 10 === 3 => 'rd',
            default => 'th',
        };

        return $n . $suffix;
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     * @return list<int>
     */
    private static function calculateWidths(array $headers, array $rows): array
    {
        $widths = \array_map(\mb_strlen(...), $headers);

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
     * @param array<int, true> $rightAlign Column indices to right-align.
     */
    private static function row(array $cells, array $widths, array $rightAlign = []): string
    {
        $parts = [];
        foreach ($cells as $i => $cell) {
            $padding = $widths[$i] - \mb_strlen($cell);
            $pad = \str_repeat(' ', $padding);
            $parts[] = isset($rightAlign[$i])
                ? ' ' . $pad . $cell . ' '
                : ' ' . $cell . $pad . ' ';
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
                    // $result .= ' ' . self::centerPad($label, $span - 2) . ' |';
                    $result .= ' ' . \str_pad(\strtoupper($label), $span - 2) . ' |';
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
     * Expand column widths so that span labels fit within their merged area.
     *
     * @param list<int> $widths
     * @param list<array{int, int, string}> $spans
     * @return list<int>
     */
    private static function adjustWidthsForSpans(array $widths, array $spans): array
    {
        foreach ($spans as [$from, $to, $label]) {
            $available = self::spanWidth($widths, $from, $to) - 2;
            $overflow = \mb_strlen($label) - $available;

            if ($overflow > 0) {
                $widths[$to] += $overflow;
            }
        }

        return $widths;
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

    /**
     * @param list<Report> $reports
     */
    private static function joinReports(array $reports, Severity $severity): string
    {
        $reasons = [];
        foreach ($reports as $report) {
            $report->severity === $severity and $reasons[] = $report->reason;
        }

        return \implode('. ', $reasons);
    }
}
