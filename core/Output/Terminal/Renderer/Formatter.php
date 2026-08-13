<?php

declare(strict_types=1);

namespace Testo\Output\Terminal\Renderer;

use Testo\Assert\State\Assertion\ComparisonFailure;
use Testo\Common\Info;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Output\Rendering\Color;
use Testo\Output\Rendering\Diff\DiffOp;
use Testo\Output\Rendering\Diff\MyersDiffer;

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

    /**
     * @var int<1, max> Width of the label column in summary blocks; the value column starts after it.
     */
    private const STAT_LABEL_WIDTH = 8;

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
        $summary = $result->summary;
        $passed = $summary->count(Status::Passed);
        $failed = $summary->count(Status::Failed);
        $error = $summary->count(Status::Error);
        $skipped = $summary->count(Status::Skipped);
        $risky = $summary->count(Status::Risky);
        $cancelled = $summary->count(Status::Cancelled);
        $flaky = $summary->count(Status::Flaky);

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
    public static function suiteSummary(SuiteResult $result): string
    {
        $parts = [];
        $summary = $result->summary;
        $passed = $summary->count(Status::Passed);
        $failed = $summary->count(Status::Failed);
        $skipped = $summary->count(Status::Skipped);
        $risky = $summary->count(Status::Risky);
        $cancelled = $summary->count(Status::Cancelled);
        $flaky = $summary->count(Status::Flaky);
        $error = $summary->count(Status::Error);
        $aborted = $summary->count(Status::Aborted);

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

        $total = $summary->total();
        $assertions = $summary->metric('assertions');
        $breakdown = \implode(', ', $parts);

        return self::statRow('Suite', Style::dim("{$total} tests · {$assertions} assertions"))
            . self::statRow('', $breakdown);
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
     * @param Summary $summary Aggregated session statistics.
     * @param float $duration Wall-clock duration in seconds.
     * @return non-empty-string
     */
    public static function summary(
        Summary $summary,
        float $duration,
    ): string {
        $parts = [];
        $passed = $summary->count(Status::Passed);
        $failed = $summary->count(Status::Failed);
        $error = $summary->count(Status::Error);
        $skipped = $summary->count(Status::Skipped);
        $risky = $summary->count(Status::Risky);
        $cancelled = $summary->count(Status::Cancelled);
        $flaky = $summary->count(Status::Flaky);
        $aborted = $summary->count(Status::Aborted);

        $passed > 0 and $parts[] = Style::success("{$passed} passed");
        $failed > 0 and $parts[] = Style::error("{$failed} failed");
        $error > 0 and $parts[] = Style::error("{$error} error");
        $skipped > 0 and $parts[] = Style::warning("{$skipped} skipped");
        $risky > 0 and $parts[] = Style::warning("{$risky} risky");
        $cancelled > 0 and $parts[] = Style::dim("{$cancelled} cancelled");
        $flaky > 0 and $parts[] = Style::info("{$flaky} flaky");
        $aborted > 0 and $parts[] = Style::error("{$aborted} aborted");

        $total = $summary->total();
        $assertions = $summary->metric('assertions');
        $breakdown = $parts === [] ? Style::dim('no tests') : \implode(', ', $parts);
        $testsTime = self::formatDuration($summary->duration);
        $overheadTime = self::formatDuration(\max(0, $duration - $summary->duration));

        $result = "\n\n " . Style::bold('Summary') . "\n\n";
        $result .= self::statRow('Time', Style::dim("{$testsTime} tests · {$overheadTime} overhead"));
        $result .= self::statRow('Total', "{$total} tests · {$assertions} assertions");

        return $result . self::statRow('', $breakdown);
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
     * Formats the banner shown when a run executed no tests. An empty run verified nothing, so it is
     * neither a pass nor a hard failure — a distinct warning banner makes that explicit.
     *
     * @return non-empty-string
     */
    public static function emptyBanner(): string
    {
        return "\n " . Style::banner('NO TESTS', Color::BgYellow, Color::Black) . "\n";
    }

    /**
     * Formats an expected/actual comparison block as a colored unified line diff.
     *
     * @return non-empty-string
     */
    public static function comparisonBlock(ComparisonFailure $failure): string
    {
        $diff = (new MyersDiffer())->diff(
            $failure->getExpectedAsString(),
            $failure->getActualAsString(),
        );

        $lines = [
            Style::dim('--- Expected'),
            Style::dim('+++ Actual'),
        ];

        foreach ($diff as $entry) {
            $lines[] = match ($entry->op) {
                DiffOp::Remove => Style::error('- ' . $entry->line),
                DiffOp::Add => Style::success('+ ' . $entry->line),
                DiffOp::Context => '  ' . $entry->line,
            };
        }

        return \implode("\n", $lines);
    }

    /**
     * Formats a description block indented under an item at the given nesting level. Returns an empty
     * string when there is no description. Used both for plain test runs and for the DataProvider
     * batch node, so the description shows once at the root of the dataset tree instead of repeating
     * under every dataset.
     */
    public static function description(string $description, int $indentLevel, OutputFormat $format): string
    {
        if ($description === '' || $format === OutputFormat::Dots) {
            return '';
        }

        $baseIndent = $format === OutputFormat::Verbose ? self::INDENT_VERBOSE : self::INDENT_COMPACT;
        $descriptionPadding = $baseIndent . \str_repeat(self::INDENT_STEP, $indentLevel) . self::INDENT_STEP;
        $descriptionStr = Style::dim(
            \str_replace("\n", "\n{$descriptionPadding}", $description),
        );

        return "{$descriptionPadding}{$descriptionStr}\n";
    }

    /**
     * Renders one row of a summary block: a dim, fixed-width label followed by its (already styled)
     * value. An empty label produces a continuation row aligned under the value column, so the parts
     * stay aligned regardless of {@see self::STAT_LABEL_WIDTH}.
     *
     * @return non-empty-string
     */
    private static function statRow(string $label, string $value): string
    {
        $label = $label === '' ? \str_repeat(' ', self::STAT_LABEL_WIDTH) : Style::dim(\str_pad($label, self::STAT_LABEL_WIDTH));

        return ' ' . $label . $value . "\n";
    }

    /**
     * Formats a duration, switching to milliseconds for sub-second values so small numbers do not
     * collapse to a useless "0.00s".
     *
     * @return non-empty-string
     */
    private static function formatDuration(float $seconds): string
    {
        if ($seconds >= 1.0) {
            return \number_format($seconds, 2) . 's';
        }

        $ms = $seconds * 1000;

        return \number_format($ms, $ms >= 1.0 ? 0 : 2) . 'ms';
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

        return $result . self::description($item->description, $item->indentLevel, $format);
    }

    /**
     * Formats a test run in dots mode.
     */
    private static function formatDotRun(FormattedItem $item): string
    {
        return match ($item->status) {
            Status::Passed => DotSymbol::Passed->value,
            Status::Failed => Style::error(DotSymbol::Failed->value),
            Status::Skipped => Style::warning(DotSymbol::Skipped->value),
            Status::Error => Style::error(DotSymbol::Error->value),
            Status::Aborted => Style::error(DotSymbol::Aborted->value),
            Status::Risky => Style::warning(DotSymbol::Risky->value),
            Status::Flaky => Style::info(DotSymbol::Passed->value),
            Status::Cancelled => Style::dim(DotSymbol::Skipped->value),
        };
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
            Status::Error => Style::error(Symbol::Error->value),
            Status::Aborted => Style::error(Symbol::Aborted->value),
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
