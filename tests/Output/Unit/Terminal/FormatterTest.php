<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Terminal;

use Testo\Assert;
use Testo\Assert\State\Assertion\ComparisonFailure;
use Testo\Codecov\Covers;
use Testo\Common\Info;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Value\RunTiming;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Data\DataSet;
use Testo\Output\Terminal\Renderer\FormattedItem;
use Testo\Output\Terminal\Renderer\Formatter;
use Testo\Output\Terminal\Renderer\OutputFormat;
use Testo\Output\Terminal\Renderer\Style;
use Testo\Test;

#[Test]
#[Covers(Formatter::class)]
final class FormatterTest
{
    public function comparisonBlockHasExpectedAndActualHeaders(): void
    {
        $failure = self::makeFailure('foo', 'bar');

        $block = Formatter::comparisonBlock($failure);

        Assert::string($block)->contains('--- Expected');
        Assert::string($block)->contains('+++ Actual');
    }

    public function comparisonBlockMarksRemovedLinesWithMinusAndAddedLinesWithPlus(): void
    {
        $failure = self::makeFailure(
            "line1\nline2\nline3",
            "line1\nlineDIFF\nline3",
        );

        $block = Formatter::comparisonBlock($failure);

        // common lines kept as context (no prefix)
        Assert::string($block)->contains('  line1');
        Assert::string($block)->contains('  line3');
        // changed line emitted as remove + add pair
        Assert::string($block)->contains('- line2');
        Assert::string($block)->contains('+ lineDIFF');
    }

    public function comparisonBlockTreatsExtraLinesInActualAsAdditions(): void
    {
        $failure = self::makeFailure(
            "line1\nline2",
            "line1\nline2\nextra",
        );

        $block = Formatter::comparisonBlock($failure);

        Assert::string($block)->contains('  line1');
        Assert::string($block)->contains('  line2');
        Assert::string($block)->contains('+ extra');
        Assert::same(self::countDiffLines($block, '-'), 0);
    }

    public function comparisonBlockTreatsMissingLinesInActualAsRemovals(): void
    {
        $failure = self::makeFailure(
            "line1\nline2\nline3",
            "line1\nline3",
        );

        $block = Formatter::comparisonBlock($failure);

        Assert::string($block)->contains('  line1');
        Assert::string($block)->contains('  line3');
        Assert::string($block)->contains('- line2');
        Assert::same(self::countDiffLines($block, '+'), 0);
    }

    public function comparisonBlockRendersArrayValuesAsPrintRDiff(): void
    {
        $failure = self::makeFailure(
            ['a' => 1, 'b' => 2],
            ['a' => 1, 'b' => 3],
        );

        $block = Formatter::comparisonBlock($failure);

        // print_r output produces an "Array\n(\n    [a] => 1\n    [b] => 2\n)"
        Assert::string($block)->contains('Array');
        Assert::string($block)->contains('- ');
        Assert::string($block)->contains('+ ');
    }

    public function comparisonBlockRendersEnumsAsClassAndCaseName(): void
    {
        $failure = self::makeFailure(Status::Passed, Status::Failed);

        $block = Formatter::comparisonBlock($failure);

        // Enums must render as `Class::Case`, not via print_r's "Status Enum (...)".
        Assert::string($block)->contains(Status::class . '::Passed');
        Assert::string($block)->contains(Status::class . '::Failed');
        Assert::string($block)->notContains('Enum');
    }

    public function emptyBannerReadsNoTests(): void
    {
        Assert::string(Formatter::emptyBanner())->contains('NO TESTS');
    }

    public function runHeaderShowsNameAndVersion(): void
    {
        $out = self::withoutColors(static fn(): string => Formatter::runHeader());

        Assert::string($out)->contains(Info::NAME)->contains('v' . Info::version());
    }

    #[DataSet([OutputFormat::Verbose, "\n Suite: MySuite\n"], 'verbose keeps a leading space')]
    #[DataSet([OutputFormat::Compact, "\nSuite: MySuite\n"], 'compact has no leading space')]
    #[DataSet([OutputFormat::Dots, "\nSuite: MySuite\n"], 'dots matches compact')]
    public function suiteHeaderRendersPerFormat(OutputFormat $format, string $expected): void
    {
        $out = self::withoutColors(static fn(): string => Formatter::suiteHeader('MySuite', $format));

        Assert::same($out, $expected);
    }

    #[DataSet([OutputFormat::Verbose, "\n   Case: MyCase\n"], 'verbose labels the case')]
    #[DataSet([OutputFormat::Compact, " MyCase\n"], 'compact shows the bare name')]
    #[DataSet([OutputFormat::Dots, " MyCase "], 'dots keeps it on one line')]
    public function caseHeaderRendersPerFormat(OutputFormat $format, string $expected): void
    {
        $out = self::withoutColors(static fn(): string => Formatter::caseHeader('MyCase', $format));

        Assert::same($out, $expected);
    }

    #[DataSet([OutputFormat::Dots, "\n"], 'dots closes the line')]
    #[DataSet([OutputFormat::Compact, ''], 'compact emits nothing')]
    #[DataSet([OutputFormat::Verbose, ''], 'verbose emits nothing')]
    public function caseFooterOnlyClosesInDotsMode(OutputFormat $format, string $expected): void
    {
        Assert::same(Formatter::caseFooter($format), $expected);
    }

    public function caseSummaryListsEveryStatusInVerboseMode(): void
    {
        $result = self::caseResult(self::allStatusesSummary());

        $out = self::withoutColors(static fn(): string => Formatter::caseSummary($result, OutputFormat::Verbose));

        Assert::string($out)
            ->contains('Summary:')
            ->contains('1 passed')
            ->contains('1 failed')
            ->contains('1 error')
            ->contains('1 skipped')
            ->contains('1 risky')
            ->contains('1 cancelled')
            ->contains('1 flaky');
    }

    public function caseSummaryReadsNoTestsWhenSummaryEmpty(): void
    {
        $result = self::caseResult(new Summary());

        $out = self::withoutColors(static fn(): string => Formatter::caseSummary($result, OutputFormat::Verbose));

        Assert::string($out)->contains('Summary: no tests');
    }

    public function caseSummaryIsEmptyOutsideVerboseMode(): void
    {
        $result = self::caseResult(self::allStatusesSummary());

        Assert::same(Formatter::caseSummary($result, OutputFormat::Compact), '');
    }

    public function suiteSummaryListsEveryStatusWithTotals(): void
    {
        $result = new SuiteResult(results: [], status: Status::Passed, summary: self::allStatusesSummary());

        $out = self::withoutColors(static fn(): string => Formatter::suiteSummary($result));

        Assert::string($out)
            ->contains('8 tests · 9 assertions')
            ->contains('1 passed')
            ->contains('1 failed')
            ->contains('1 error')
            ->contains('1 skipped')
            ->contains('1 risky')
            ->contains('1 cancelled')
            ->contains('1 flaky')
            ->contains('1 aborted');
    }

    public function suiteSummaryIsEmptyWhenNoTests(): void
    {
        $result = new SuiteResult(results: [], status: Status::Passed, summary: new Summary());

        Assert::same(Formatter::suiteSummary($result), '');
    }

    public function progressReportsCompletedOverTotal(): void
    {
        $out = self::withoutColors(static fn(): string => Formatter::progress(3, 10));

        Assert::string($out)->contains('Progress: 3/10 tests completed');
    }

    public function failuresHeaderReadsFailures(): void
    {
        $out = self::withoutColors(static fn(): string => Formatter::failuresHeader());

        Assert::string($out)->contains('Failures:');
    }

    public function failureDetailIncludesDurationLocationAndIndentedDetails(): void
    {
        $out = self::withoutColors(static fn(): string => Formatter::failureDetail(
            index: 1,
            testName: 'MyTest',
            message: 'boom',
            details: "line a\n\nline b",
            duration: 12,
            location: 'file.php:10',
        ));

        Assert::string($out)
            ->contains('1) MyTest')
            ->contains('(12ms)')
            ->contains('file.php:10')
            ->contains('boom')
            ->contains('    line a')
            ->contains('    line b');
    }

    public function failureDetailOmitsDurationLocationAndDetailsWhenAbsent(): void
    {
        $out = self::withoutColors(static fn(): string => Formatter::failureDetail(
            index: 2,
            testName: 'Other',
            message: 'msg',
            details: '',
            duration: null,
        ));

        Assert::string($out)
            ->contains('2) Other')
            ->contains('msg')
            ->notContains('ms)')
            ->notContains('file.php');
    }

    public function summaryRendersOverheadAndListsEveryStatus(): void
    {
        $summary = self::allStatusesSummary();
        $timing = new RunTiming(startup: 0.1, discovery: 0.2, tests: 3.0, teardown: 0.1);

        $out = self::withoutColors(static fn(): string => Formatter::summary($summary, $timing));

        Assert::string($out)
            ->contains('8 tests · 9 assertions')
            ->contains('2.50s tests')
            ->contains('500ms overhead')
            ->contains('3.40s total')
            ->contains('1 passed')
            ->contains('1 aborted');
    }

    public function summaryRendersWallWhenTestsOverlapped(): void
    {
        $summary = new Summary(counts: [Status::Passed->name => 1], duration: 5.0);
        $timing = new RunTiming(tests: 2.0);

        $out = self::withoutColors(static fn(): string => Formatter::summary($summary, $timing));

        Assert::string($out)->contains('2.00s wall');
    }

    public function summaryFormatsSubMillisecondDurationsWithTwoDecimals(): void
    {
        $summary = new Summary(counts: [Status::Passed->name => 1], duration: 0.0004);
        $timing = new RunTiming(tests: 0.001);

        $out = self::withoutColors(static fn(): string => Formatter::summary($summary, $timing));

        Assert::string($out)->contains('0.40ms tests');
    }

    public function summaryReadsNoTestsWhenEmpty(): void
    {
        $out = self::withoutColors(static fn(): string => Formatter::summary(new Summary(), new RunTiming()));

        Assert::string($out)->contains('no tests');
    }

    #[DataSet([true, 'PASSED'], 'success banner')]
    #[DataSet([false, 'FAILED'], 'failure banner')]
    public function finalBannerReflectsOutcome(bool $success, string $expected): void
    {
        $out = self::withoutColors(static fn(): string => Formatter::finalBanner($success));

        Assert::string($out)->contains($expected);
    }

    #[DataSet([Status::Passed, '.'], 'passed dot')]
    #[DataSet([Status::Failed, 'F'], 'failed dot')]
    #[DataSet([Status::Skipped, '-'], 'skipped dot')]
    #[DataSet([Status::Error, 'E'], 'error dot')]
    #[DataSet([Status::Aborted, 'A'], 'aborted dot')]
    #[DataSet([Status::Risky, 'R'], 'risky dot')]
    #[DataSet([Status::Flaky, '.'], 'flaky reuses the passed dot')]
    #[DataSet([Status::Cancelled, '-'], 'cancelled reuses the skipped dot')]
    public function formatRunInDotsModeRendersStatusSymbol(Status $status, string $expected): void
    {
        $item = new FormattedItem(name: 'MyTest', status: $status);

        $out = self::withoutColors(static fn(): string => Formatter::formatRun($item, OutputFormat::Dots));

        Assert::same($out, $expected);
    }

    #[DataSet([Status::Passed, '✓'], 'passed symbol')]
    #[DataSet([Status::Failed, '✗'], 'failed symbol')]
    #[DataSet([Status::Skipped, '○'], 'skipped symbol')]
    #[DataSet([Status::Error, 'E'], 'error symbol')]
    #[DataSet([Status::Aborted, 'A'], 'aborted symbol')]
    #[DataSet([Status::Risky, '?'], 'risky symbol')]
    #[DataSet([Status::Flaky, '~'], 'flaky symbol')]
    #[DataSet([Status::Cancelled, '○'], 'cancelled reuses the skipped symbol')]
    public function formatRunInCompactModeShowsStatusSymbolAndName(Status $status, string $symbol): void
    {
        $item = new FormattedItem(name: 'MyTest', status: $status);

        $out = self::withoutColors(static fn(): string => Formatter::formatRun($item, OutputFormat::Compact));

        Assert::string($out)->contains("{$symbol} MyTest");
    }

    public function formatRunInVerboseModeShowsSymbolIndentAndDescription(): void
    {
        $item = new FormattedItem(name: 'VTest', status: Status::Passed, description: 'note');

        $out = self::withoutColors(static fn(): string => Formatter::formatRun($item, OutputFormat::Verbose));

        Assert::string($out)->contains('✓ VTest')->contains('note');
    }

    public function formatRunShowsDurationWhenPresent(): void
    {
        $item = new FormattedItem(name: 'Timed', status: Status::Passed, duration: 12);

        $out = self::withoutColors(static fn(): string => Formatter::formatRun($item, OutputFormat::Compact));

        Assert::string($out)->contains('(12ms)');
    }

    #[DataSet(['', OutputFormat::Verbose], 'blank description yields nothing')]
    #[DataSet(['some text', OutputFormat::Dots], 'dots mode has no room for descriptions')]
    public function descriptionIsEmptyWhenBlankOrDots(string $description, OutputFormat $format): void
    {
        Assert::same(Formatter::description($description, 0, $format), '');
    }

    public function descriptionIndentsUnderItemInVerboseMode(): void
    {
        $out = self::withoutColors(static fn(): string => Formatter::description('note', 0, OutputFormat::Verbose));

        // INDENT_VERBOSE (5) + one INDENT_STEP (2) = 7 spaces
        Assert::same($out, "       note\n");
    }

    public function descriptionUsesCompactIndentInCompactMode(): void
    {
        $out = self::withoutColors(static fn(): string => Formatter::description('note', 0, OutputFormat::Compact));

        // INDENT_COMPACT (3) + one INDENT_STEP (2) = 5 spaces
        Assert::same($out, "     note\n");
    }

    public function descriptionIndentsWrappedLinesOfMultilineText(): void
    {
        $out = self::withoutColors(
            static fn(): string => Formatter::description("first\nsecond", 1, OutputFormat::Verbose),
        );

        // level 1: INDENT_VERBOSE (5) + INDENT_STEP*1 (2) + INDENT_STEP (2) = 9 spaces on every line
        Assert::same($out, "         first\n         second\n");
    }

    /**
     * Count diff body lines that begin with the given marker (`-` or `+`).
     * Header lines (`--- Expected`, `+++ Actual`) are skipped.
     *
     * @param non-empty-string $marker
     * @return int<0, max>
     */
    private static function countDiffLines(string $block, string $marker): int
    {
        $count = 0;
        foreach (\explode("\n", $block) as $line) {
            if (\str_starts_with($line, '--- ') || \str_starts_with($line, '+++ ')) {
                continue;
            }
            \str_starts_with($line, $marker . ' ') and $count++;
        }
        return $count;
    }

    private static function makeFailure(mixed $expected, mixed $actual): ComparisonFailure
    {
        return new ComparisonFailure(
            expected: $expected,
            actual: $actual,
            value: 'value',
            assertion: 'is the same',
            context: '',
            reason: 'values differ',
        );
    }

    /**
     * A summary carrying exactly one test of every status plus an assertion metric, so every
     * status branch of the summary formatters has something to render.
     */
    private static function allStatusesSummary(): Summary
    {
        return new Summary(
            counts: [
                Status::Passed->name => 1,
                Status::Failed->name => 1,
                Status::Error->name => 1,
                Status::Skipped->name => 1,
                Status::Risky->name => 1,
                Status::Cancelled->name => 1,
                Status::Flaky->name => 1,
                Status::Aborted->name => 1,
            ],
            metrics: ['assertions' => 9],
            duration: 2.5,
        );
    }

    private static function caseResult(Summary $summary): CaseResult
    {
        return new CaseResult(results: [], status: Status::Passed, summary: $summary);
    }

    /**
     * Renders with colorization forced off so assertions read plain text. Colorization is a
     * process-global flag in {@see Style}, so the previous value is restored to avoid leaking
     * into whatever case runs next.
     *
     * @param \Closure(): string $render
     */
    private static function withoutColors(\Closure $render): string
    {
        $colors = Style::areColorsEnabled();
        Style::setColorsEnabled(false);

        try {
            return $render();
        } finally {
            Style::setColorsEnabled($colors);
        }
    }
}
