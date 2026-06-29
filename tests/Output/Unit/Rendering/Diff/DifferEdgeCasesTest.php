<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Rendering\Diff;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Output\Rendering\Diff\DiffOp;
use Testo\Output\Rendering\Diff\HirschbergDiffer;
use Testo\Output\Rendering\Diff\PrefixSuffixDiffer;
use Testo\Output\Rendering\Diff\RatcliffObershelpDiffer;
use Testo\Test;

#[Test]
#[Covers(PrefixSuffixDiffer::class)]
#[Covers(HirschbergDiffer::class)]
#[Covers(RatcliffObershelpDiffer::class)]
final class DifferEdgeCasesTest
{
    public function prefixSuffixDifferDefaultInnerDiffer(): void
    {
        $differ = new PrefixSuffixDiffer();
        $diff = $differ->diff("a\nb\nc", "a\nX\nc");

        Assert::true(\count($diff) > 0);
    }

    public function hirschbergDifferEmptyLeftSide(): void
    {
        $differ = new HirschbergDiffer();
        $diff = $differ->diff("", "x\ny\nz");

        $adds = \array_filter($diff, static fn($line) => $line->op === DiffOp::Add);
        Assert::same(\count($adds), 3);
    }

    public function hirschbergDifferSingleRowInLeftSide(): void
    {
        $differ = new HirschbergDiffer();
        $diff = $differ->diff("single", "single\nrow");

        Assert::true(\count($diff) > 0);
    }

    public function hirschbergDifferEmptyRightSide(): void
    {
        $differ = new HirschbergDiffer();
        $diff = $differ->diff("a\nb\nc", "");

        $removes = \array_filter($diff, static fn($line) => $line->op === DiffOp::Remove);
        Assert::same(\count($removes), 3);
    }

    public function hirschbergDifferAsymmetricLargeExpected(): void
    {
        $differ = new HirschbergDiffer();
        $expected = \implode("\n", \array_fill(0, 20, "line"));
        $actual = "different";

        $diff = $differ->diff($expected, $actual);

        $removes = \array_filter($diff, static fn($line) => $line->op === DiffOp::Remove);
        $adds = \array_filter($diff, static fn($line) => $line->op === DiffOp::Add);
        Assert::true(\count($removes) > 0 && \count($adds) > 0);
    }

    public function hirschbergDifferComplexRecursion(): void
    {
        $differ = new HirschbergDiffer();
        $expected = "a\nb\nc\nd\ne\nf\ng\nh";
        $actual = "a\nX\nc\nY\ne\nZ\ng\nh";

        $diff = $differ->diff($expected, $actual);

        $edits = \array_filter($diff, static fn($line) => $line->op !== DiffOp::Context);
        Assert::true(\count($edits) > 0);
    }

    public function ratcliffObershelpDifferDefaultInnerDiffer(): void
    {
        $differ = new RatcliffObershelpDiffer();
        $diff = $differ->diff("a\nb\nc", "a\nX\nc");

        Assert::true(\count($diff) > 0);
    }

    public function ratcliffObershelpDifferManyMatches(): void
    {
        $differ = new RatcliffObershelpDiffer();
        $expected = "line1\nline2\nline3\nline4\nline5";
        $actual = "line1\nline2\nCHANGED\nline4\nline5";

        $diff = $differ->diff($expected, $actual);

        $contexts = \array_filter($diff, static fn($line) => $line->op === DiffOp::Context);
        Assert::true(\count($contexts) >= 3);
    }

    public function ratcliffObershelpDifferNoMatches(): void
    {
        $differ = new RatcliffObershelpDiffer();
        $diff = $differ->diff("aaa", "bbb");

        $removes = \array_filter($diff, static fn($line) => $line->op === DiffOp::Remove);
        $adds = \array_filter($diff, static fn($line) => $line->op === DiffOp::Add);
        Assert::true(\count($removes) > 0 && \count($adds) > 0);
    }

    public function ratcliffObershelpDifferEmptyStrings(): void
    {
        $differ = new RatcliffObershelpDiffer();
        $diff = $differ->diff("", "");

        // explode("\n", "") yields [""], not [], so both sides are identical: exactly one context line.
        Assert::count($diff, 1);
        Assert::same($diff[0]->op, DiffOp::Context);
    }

    public function ratcliffObershelpDifferOneEmptyString(): void
    {
        $differ = new RatcliffObershelpDiffer();
        $diff = $differ->diff("abc", "");

        $removes = \array_filter($diff, static fn($line) => $line->op === DiffOp::Remove);
        Assert::true(\count($removes) > 0);
    }

    public function ratcliffObershelpDifferSingleCharDiff(): void
    {
        $differ = new RatcliffObershelpDiffer();
        $diff = $differ->diff("a", "b");

        Assert::true(\count($diff) > 0);
    }

    public function ratcliffObershelpDifferVerySmallDiff(): void
    {
        $differ = new RatcliffObershelpDiffer();
        $diff = $differ->diff("same\ntext\nhere", "same\ntext\nchanged");

        $contexts = \array_filter($diff, static fn($line) => $line->op === DiffOp::Context);
        Assert::true(\count($contexts) >= 2);
    }

    public function ratcliffObershelpDifferBlockExpansion(): void
    {
        $differ = new RatcliffObershelpDiffer();
        $expected = "prefix\nmatch\nchange1\nmatch\nsuffix";
        $actual = "prefix\nmatch\nchange2\nmatch\nsuffix";

        $diff = $differ->diff($expected, $actual);

        $contexts = \array_filter($diff, static fn($line) => $line->op === DiffOp::Context);
        $removes = \array_filter($diff, static fn($line) => $line->op === DiffOp::Remove);
        $adds = \array_filter($diff, static fn($line) => $line->op === DiffOp::Add);

        Assert::true(\count($contexts) >= 2 && \count($removes) > 0 && \count($adds) > 0);
    }

    public function ratcliffObershelpDifferJunkDetection(): void
    {
        $differ = new RatcliffObershelpDiffer();
        $lines = \array_fill(0, 100, "repeated");
        $repeated = \implode("\n", $lines);
        $expected = $repeated . "\nunique\nchange";
        $actual = $repeated . "\nunique\nsame";

        $diff = $differ->diff($expected, $actual);

        Assert::true(\count($diff) > 0);
    }

    public function ratcliffObershelpDifferBoundaryMatching(): void
    {
        $differ = new RatcliffObershelpDiffer();
        $expected = "a\nb\nc\nd\ne";
        $actual = "a\nb\nX\nd\ne";

        $diff = $differ->diff($expected, $actual);

        $edits = \array_filter($diff, static fn($line) => $line->op !== DiffOp::Context);
        Assert::true(\count($edits) > 0);
    }

    public function ratcliffObershelpDifferBlockGrowth(): void
    {
        $differ = new RatcliffObershelpDiffer();
        $expected = "ctx1\nctx2\nmatch\nmatch\nmatch\nctx3\nctx4";
        $actual = "ctx1\nctx2\nchange\nmatch\nmatch\nctx3\nctx4";

        $diff = $differ->diff($expected, $actual);

        $contexts = \array_filter($diff, static fn($line) => $line->op === DiffOp::Context);
        Assert::true(\count($contexts) >= 4);
    }

    public function ratcliffObershelpDifferNoBoundaryOverrun(): void
    {
        $differ = new RatcliffObershelpDiffer();
        $expected = "start\nchange1\nend";
        $actual = "start\nchange2\nend";

        $diff = $differ->diff($expected, $actual);

        $contexts = \array_filter($diff, static fn($line) => $line->op === DiffOp::Context);
        Assert::true(\count($contexts) >= 2);
    }

    public function ratcliffObershelpDifferComplexWithJunk(): void
    {
        $differ = new RatcliffObershelpDiffer();
        $junk_lines = \array_fill(0, 50, "the");
        $expected = \implode("\n", $junk_lines) . "\nunique_start\nmiddle\nunique_end";
        $actual = \implode("\n", $junk_lines) . "\nunique_start\nchanged\nunique_end";

        $diff = $differ->diff($expected, $actual);

        Assert::true(\count($diff) > 0);
    }

    public function ratcliffObershelpDifferVeryLargeWithAutoJunk(): void
    {
        $differ = new RatcliffObershelpDiffer();
        // 201 lines crosses the m >= 200 autoJunk threshold.
        $lines = \array_merge(
            \array_fill(0, 100, "common"),
            ["unique_expected"],
            \array_fill(0, 100, "common")
        );
        $expected = \implode("\n", $lines);

        $lines_actual = \array_merge(
            \array_fill(0, 100, "common"),
            ["unique_actual"],
            \array_fill(0, 100, "common")
        );
        $actual = \implode("\n", $lines_actual);

        $diff = $differ->diff($expected, $actual);

        Assert::true(\count($diff) > 0);
    }

    public function hirschbergDifferRecursiveEmptyRange(): void
    {
        $differ = new HirschbergDiffer();
        $expected = "a\na\na\nb\nb\nb\nc\nc\nc";
        $actual = "c\nc\nc";

        $diff = $differ->diff($expected, $actual);

        $removes = \array_filter($diff, static fn($line) => $line->op === DiffOp::Remove);
        $adds = \array_filter($diff, static fn($line) => $line->op === DiffOp::Add);

        Assert::true(\count($removes) > 0);
    }
}
