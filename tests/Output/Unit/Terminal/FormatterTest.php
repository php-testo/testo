<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Terminal;

use Testo\Assert;
use Testo\Assert\State\Assertion\ComparisonFailure;
use Testo\Core\Value\Status;
use Testo\Output\Terminal\Renderer\Formatter;
use Testo\Test;

#[Test]
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
}
