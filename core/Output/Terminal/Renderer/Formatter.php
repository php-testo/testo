<?php

declare(strict_types=1);

namespace Testo\Output\Terminal\Renderer;

use Testo\Assert\State\Assertion\ComparisonFailure;
use Testo\Common\Info;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Value\Status;
use Testo\Output\Rendering\Color;

/**
 * Formats terminal output messages with support for different output formats.
 *
 * @internal
 */
final class Formatter
{
    /**
     * @var non-empty-string Base indentation for verbose mode
     */
    private const INDENT_VERBOSE = '     ';

    /**
     * @var non-empty-string Base indentation for compact mode
     */
    private const INDENT_COMPACT = '   ';

    /**
     * @var non-empty-string Indentation step for nested levels
     */
    private const INDENT_STEP = '  ';

    private function __construct() {}

    /**
     * Formats a test run item (test, data provider run, etc).
     *
     * @return non-empty-string
     */
    public static function formatRun(FormattedItem $item, OutputFormat $format): string
    {
        return match ($format) {
            OutputFormat::Compact, OutputFormat::Verbose => self::formatCompactRun($item, $format),
            OutputFormat::Dots => self::formatDotRun($item),
        };
    }

    /**
     * Formats the header for starting test run.
     *
     * @return non-empty-string
     */
    public static function runHeader(): string
    {
        $name = Info::NAME;
        $version = Info::version();
        return Style::bold("{$name}") . Style::dim(" v{$version}") . "\n\n";
    }

    /**
     * Formats a suite header.
     *
     * @param non-empty-string $name
     * @return non-empty-string
     */
    public static function suiteHeader(string $name, OutputFormat $format): string
    {
        return match ($format) {
            OutputFormat::Verbose => "\n " . Style::bold("Suite: {$name}") . "\n",
            OutputFormat::Compact => "\n" . Style::bold("Suite: {$name}") . "\n",
            OutputFormat::Dots => "\n" . Style::bold("Suite: {$name}") . "\n",
        };
    }

    /**
     * Formats a test case header.
     *
     * @param non-empty-string $name
     * @return non-empty-string
     */
    public static function caseHeader(string $name, OutputFormat $format): string
    {
        return match ($format) {
            OutputFormat::Verbose => "\n   " . Style::bold("Case: {$name}") . "\n",
            OutputFormat::Compact => " " . Style::bold($name) . "\n",
            OutputFormat::Dots => " " . Style::bold($name) . " ",
        };
    }

    /**
     * Formats case footer (dots mode).
     */
    public static function caseFooter(OutputFormat $format): string
    {
        return $format === OutputFormat::Dots ? "\n" : '';
    }

    /**
     * Formats case summary (verbose mode only).
     */
    public static function caseSummary(CaseResult $result, OutputFormat $format): string
    {
        if ($format !== OutputFormat::Verbose) {
            return '';
        }

        $parts = [];
        $passed = $result->countTests(Status::Passed);
        $failed = $result->countTests(Status::Failed);
        $error = $result->countTests(Status::Error);
        $skipped = $result->countTests(Status::Skipped);
        $risky = $result->countTests(Status::Risky);
        $cancelled = $result->countTests(Status::Cancelled);
        $flaky = $result->countTests(Status::Flaky);

        $passed > 0 and $parts[] = Style::success("{$passed} passed");
        $failed > 0 and $parts[] = Style::error("{$failed} failed");
        $error > 0 and $parts[] = Style::error("{$error} error");
        $skipped > 0 and $parts[] = Style::warning("{$skipped} skipped");
        $risky > 0 and $parts[] = Style::warning("{$risky} risky");
        $cancelled > 0 and $parts[] = Style::dim("{$cancelled} cancelled");
        $flaky > 0 and $parts[] = Style::info("{$flaky} flaky");

        $parts === [] and $parts[] = 'no tests';

        $summary = \implode(', ', $parts);
        return "   " . Style::dim("Summary: {$summary}") . "\n";
    }

    /**
     * Formats suite summary.
     */
    public static function suiteSummary(SuiteResult $result, OutputFormat $format): string
    {
        $parts = [];
        $passed = $result->countTests(Status::Passed);
        $failed = $result->countTests(Status::Failed);
        $skipped = $result->countTests(Status::Skipped);
        $risky = $result->countTests(Status::Risky);
        $cancelled = $result->countTests(Status::Cancelled);
        $flaky = $result->countTests(Status::Flaky);
        $error = $result->countTests(Status::Error);
        $aborted = $result->countTests(Status::Aborted);

        $passed > 0 and $parts[] = Style::success("{$passed} passed");
        $failed > 0 and $parts[] = Style::error("{$failed} failed");
        $error > 0 and $parts[] = Style::error("{$error} error");
        $skipped > 0 and $parts[] = Style::warning("{$skipped} skipped");
        $risky > 0 and $parts[] = Style::warning("{$risky} risky");
        $cancelled > 0 and $parts[] = Style::dim("{$cancelled} cancelled");
        $flaky > 0 and $parts[] = Style::info("{$flaky} flaky");
        $aborted > 0 and $parts[] = Style::error("{$aborted} aborted");

        if ($parts === []) {
            return '';
        }

        $total = $passed + $failed + $error + $skipped + $risky + $cancelled + $flaky + $aborted;
        $summary = \implode(', ', $parts);
        $prefix = $format === OutputFormat::Verbose ? ' ' : '';

        return "{$prefix}" . Style::dim("Suite: {$total} tests")
            . "\n{$prefix}   " . Style::dim($summary)
            . "\n";
    }

    /**
     * Formats progress indicator.
     *
     * @param int<0, max> $current
     * @param int<0, max> $total
     * @return non-empty-string
     */
    public static function progress(int $current, int $total): string
    {
        return "\n " . Style::dim("Progress: {$current}/{$total} tests completed") . "\n";
    }

    /**
     * Formats failures section header.
     *
     * @return non-empty-string
     */
    public static function failuresHeader(): string
    {
        return "\n\n " . Style::bold(Style::error('Failures:')) . "\n";
    }

    /**
     * Formats a single failure detail.
     *
     * @param int<1, max> $index
     * @param non-empty-string $testName
     * @param non-empty-string $message
     * @param non-empty-string $details
     * @param int<0, max>|null $duration
     * @return non-empty-string
     */
    public static function failureDetail(
        int $index,
        string $testName,
        string $message,
        string $details,
        ?int $duration,
        ?string $location = null,
    ): string {
        $durationStr = $duration !== null
            ? Style::dim(" ({$duration}ms)")
            : '';

        $header = "\n " . Style::bold("{$index}) {$testName}") . $durationStr . "\n";
        $locationBlock = $location !== null ? "    " . Style::dim($location) . "\n" : '';
        $messageBlock = "\n    {$message}\n";
        $detailsBlock = $details !== '' ? "\n" . self::indentText($details, '    ') . "\n" : '';

        return $header . $locationBlock . $messageBlock . $detailsBlock;
    }

    /**
     * Formats final summary section.
     *
     * @param int<0, max> $total
     * @param int<0, max> $total
     * @param array<string, int<0, max>> $statusCounts Counts keyed by Status::name
     * @param float $duration Duration in seconds
     * @return non-empty-string
     */
    public static function summary(
        int $total,
        array $statusCounts,
        float $duration,
    ): string {
        $parts = [];
        $passed = $statusCounts[Status::Passed->name] ?? 0;
        $failed = $statusCounts[Status::Failed->name] ?? 0;
        $error = $statusCounts[Status::Error->name] ?? 0;
        $skipped = $statusCounts[Status::Skipped->name] ?? 0;
        $risky = $statusCounts[Status::Risky->name] ?? 0;
        $cancelled = $statusCounts[Status::Cancelled->name] ?? 0;
        $flaky = $statusCounts[Status::Flaky->name] ?? 0;
        $aborted = $statusCounts[Status::Aborted->name] ?? 0;

        $passed > 0 and $parts[] = Style::success("{$passed} passed");
        $failed > 0 and $parts[] = Style::error("{$failed} failed");
        $error > 0 and $parts[] = Style::error("{$error} error");
        $skipped > 0 and $parts[] = Style::warning("{$skipped} skipped");
        $risky > 0 and $parts[] = Style::warning("{$risky} risky");
        $cancelled > 0 and $parts[] = Style::dim("{$cancelled} cancelled");
        $flaky > 0 and $parts[] = Style::info("{$flaky} flaky");
        $aborted > 0 and $parts[] = Style::error("{$aborted} aborted");

        $breakdown = $parts === [] ? 'no tests' : \implode(', ', $parts);
        $durationFormatted = \number_format($duration, 2);

        $summary = "\n\n " . Style::bold('Summary') . "\n\n";
        $summary .= " Tests:    {$total} total\n";
        $summary .= "           {$breakdown}\n";
        $summary .= " Duration: {$durationFormatted}s\n";

        return $summary;
    }

    /**
     * Formats final status banner.
     *
     * @return non-empty-string
     */
    public static function finalBanner(bool $success): string
    {
        $bg = $success ? Color::BgGreen : Color::BgRed;
        $text = $success ? 'PASSED' : 'FAILED';

        return "\n " . Style::banner($text, $bg) . "\n";
    }

    /**
     * Formats an expected/actual comparison block as a colored unified line diff.
     *
     * @return non-empty-string
     */
    public static function comparisonBlock(ComparisonFailure $failure): string
    {
        $diff = self::computeLineDiff(
            $failure->getExpectedAsString(),
            $failure->getActualAsString(),
        );

        $lines = [
            Style::dim('--- Expected'),
            Style::dim('+++ Actual'),
        ];

        foreach ($diff as $entry) {
            $lines[] = match ($entry['type']) {
                'remove' => Style::error('- ' . $entry['line']),
                'add' => Style::success('+ ' . $entry['line']),
                'context' => '  ' . $entry['line'],
            };
        }

        return \implode("\n", $lines);
    }

    /**
     * Computes a line-by-line diff using LCS backtracking.
     *
     * @return list<array{type: 'context'|'remove'|'add', line: string}>
     */
    private static function computeLineDiff(string $expected, string $actual): array
    {
        $a = \explode("\n", $expected);
        $b = \explode("\n", $actual);

        $m = \count($a);
        $n = \count($b);
        $lcs = \array_fill(0, $m + 1, \array_fill(0, $n + 1, 0));

        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                $lcs[$i][$j] = $a[$i - 1] === $b[$j - 1]
                    ? $lcs[$i - 1][$j - 1] + 1
                    : \max($lcs[$i - 1][$j], $lcs[$i][$j - 1]);
            }
        }

        $diff = [];
        $i = $m;
        $j = $n;
        while ($i > 0 && $j > 0) {
            if ($a[$i - 1] === $b[$j - 1]) {
                \array_unshift($diff, ['type' => 'context', 'line' => $a[$i - 1]]);
                $i--;
                $j--;
            } elseif ($lcs[$i - 1][$j] >= $lcs[$i][$j - 1]) {
                \array_unshift($diff, ['type' => 'remove', 'line' => $a[$i - 1]]);
                $i--;
            } else {
                \array_unshift($diff, ['type' => 'add', 'line' => $b[$j - 1]]);
                $j--;
            }
        }
        while ($i > 0) {
            \array_unshift($diff, ['type' => 'remove', 'line' => $a[$i - 1]]);
            $i--;
        }
        while ($j > 0) {
            \array_unshift($diff, ['type' => 'add', 'line' => $b[$j - 1]]);
            $j--;
        }

        return $diff;
    }

    /**
     * Formats a test run in compact/verbose mode.
     */
    private static function formatCompactRun(FormattedItem $item, OutputFormat $format): string
    {
        $symbol = self::getStatusSymbol($item->status);
        $baseIndent = $format === OutputFormat::Verbose ? self::INDENT_VERBOSE : self::INDENT_COMPACT;
        $indent = $baseIndent . \str_repeat(self::INDENT_STEP, $item->indentLevel);

        $durationStr = $item->duration !== null
            ? Style::dim(" ({$item->duration}ms)")
            : '';

        $result = "{$indent}{$symbol} {$item->name}{$durationStr}\n";

        if ($item->description !== '') {
            $descriptionPadding = "{$indent}  ";
            $descriptionStr = Style::dim(
                \str_replace("\n", "\n{$descriptionPadding}", $item->description),
            );
            $result .= "{$descriptionPadding}{$descriptionStr}\n";
        }

        return $result;
    }

    /**
     * Formats a test run in dots mode.
     */
    private static function formatDotRun(FormattedItem $item): string
    {
        $symbol = match ($item->status) {
            Status::Passed => DotSymbol::Passed->value,
            Status::Failed => Style::error(DotSymbol::Failed->value),
            Status::Skipped => Style::warning(DotSymbol::Skipped->value),
            Status::Error, Status::Aborted => Style::error(DotSymbol::Error->value),
            Status::Risky => Style::warning(DotSymbol::Risky->value),
            Status::Flaky => Style::info(DotSymbol::Passed->value),
            Status::Cancelled => Style::dim(DotSymbol::Skipped->value),
        };

        return $symbol;
    }

    /**
     * Gets colored status symbol.
     */
    private static function getStatusSymbol(Status $status): string
    {
        return match ($status) {
            Status::Passed => Style::success(Symbol::Success->value),
            Status::Failed => Style::error(Symbol::Failure->value),
            Status::Skipped => Style::warning(Symbol::Skipped->value),
            Status::Error, Status::Aborted => Style::error(Symbol::Error->value),
            Status::Risky => Style::warning(Symbol::Risky->value),
            Status::Flaky => Style::info(Symbol::Flaky->value),
            Status::Cancelled => Style::dim(Symbol::Skipped->value),
        };
    }

    /**
     * Indents each line of text.
     *
     * @param non-empty-string $text
     * @param non-empty-string $indent
     */
    private static function indentText(string $text, string $indent): string
    {
        $lines = \explode("\n", $text);
        $indentedLines = \array_map(
            static fn(string $line): string => $line !== '' ? $indent . $line : '',
            $lines,
        );

        return \implode("\n", $indentedLines);
    }
}
