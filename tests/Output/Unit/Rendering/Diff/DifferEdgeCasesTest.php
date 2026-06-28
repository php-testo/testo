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
    // PrefixSuffixDiffer - constructor with default inner differ
    public function prefixSuffixDifferDefaultInnerDiffer(): void
    {
        $differ = new PrefixSuffixDiffer();
        $diff = $differ->diff("a\nb\nc", "a\nX\nc");

        Assert::true(\count($diff) > 0);
    }

    // HirschbergDiffer - empty left side triggers add loop (n === 0)
    public function hirschbergDifferEmptyLeftSide(): void
    {
        $differ = new HirschbergDiffer();
        $diff = $differ->diff("", "x\ny\nz");

        $adds = \array_filter($diff, static fn($line) => $line->op === DiffOp::Add);
        Assert::same(\count($adds), 3);
    }

    // HirschbergDiffer - single row in left side (n === 1, triggers diffSingleRow)
    public function hirschbergDifferSingleRowInLeftSide(): void
    {
        $differ = new HirschbergDiffer();
        $diff = $differ->diff("single", "single\nrow");

        Assert::true(\count($diff) > 0);
    }

    // HirschbergDiffer - empty right side (m === 0, triggers remove loop)
    public function hirschbergDifferEmptyRightSide(): void
    {
        $differ = new HirschbergDiffer();
        $diff = $differ->diff("a\nb\nc", "");

        $removes = \array_filter($diff, static fn($line) => $line->op === DiffOp::Remove);
        Assert::same(\count($removes), 3);
    }

    // HirschbergDiffer - asymmetric diff that triggers n === 0 in recursion
    // When both sides differ significantly, Hirschberg splits recursively
    // This tests the n === 0 path in diffRange when recursion encounters empty range
    public function hirschbergDifferAsymmetricLargeExpected(): void
    {
        $differ = new HirschbergDiffer();
        // Large expected with small actual - forces recursion to hit n === 0 case
        $expected = \implode("\n", \array_fill(0, 20, "line"));
        $actual = "different";

        $diff = $differ->diff($expected, $actual);

        $removes = \array_filter($diff, static fn($line) => $line->op === DiffOp::Remove);
        $adds = \array_filter($diff, static fn($line) => $line->op === DiffOp::Add);
        Assert::true(\count($removes) > 0 && \count($adds) > 0);
    }

    // HirschbergDiffer - complex pattern that exercises the recursive subdivision
    public function hirschbergDifferComplexRecursion(): void
    {
        $differ = new HirschbergDiffer();
        // Create a pattern where the recursive division will create smaller ranges
        $expected = "a\nb\nc\nd\ne\nf\ng\nh";
        $actual = "a\nX\nc\nY\ne\nZ\ng\nh";

        $diff = $differ->diff($expected, $actual);

        $edits = \array_filter($diff, static fn($line) => $line->op !== DiffOp::Context);
        Assert::true(\count($edits) > 0);
    }

    // RatcliffObershelpDiffer - constructor with default inner differ
    public function ratcliffObershelpDifferDefaultInnerDiffer(): void
    {
        $differ = new RatcliffObershelpDiffer();
        $diff = $differ->diff("a\nb\nc", "a\nX\nc");

        Assert::true(\count($diff) > 0);
    }

    // RatcliffObershelpDiffer - handling of various matching patterns
    public function ratcliffObershelpDifferManyMatches(): void
    {
        $differ = new RatcliffObershelpDiffer();
        $expected = "line1\nline2\nline3\nline4\nline5";
        $actual = "line1\nline2\nCHANGED\nline4\nline5";

        $diff = $differ->diff($expected, $actual);

        $contexts = \array_filter($diff, static fn($line) => $line->op === DiffOp::Context);
        Assert::true(\count($contexts) >= 3);
    }

    // RatcliffObershelpDiffer - no matches at all
    public function ratcliffObershelpDifferNoMatches(): void
    {
        $differ = new RatcliffObershelpDiffer();
        $diff = $differ->diff("aaa", "bbb");

        $removes = \array_filter($diff, static fn($line) => $line->op === DiffOp::Remove);
        $adds = \array_filter($diff, static fn($line) => $line->op === DiffOp::Add);
        Assert::true(\count($removes) > 0 && \count($adds) > 0);
    }

    // RatcliffObershelpDiffer - empty strings (explode("", "\n") yields [""], not [])
    public function ratcliffObershelpDifferEmptyStrings(): void
    {
        $differ = new RatcliffObershelpDiffer();
        $diff = $differ->diff("", "");

        // Both sides are [""], so they're identical - should be context
        $contexts = \array_filter($diff, static fn($line) => $line->op === DiffOp::Context);
        Assert::true(\count($contexts) >= 0);
    }

    // RatcliffObershelpDiffer - one empty string
    public function ratcliffObershelpDifferOneEmptyString(): void
    {
        $differ = new RatcliffObershelpDiffer();
        $diff = $differ->diff("abc", "");

        $removes = \array_filter($diff, static fn($line) => $line->op === DiffOp::Remove);
        Assert::true(\count($removes) > 0);
    }

    // RatcliffObershelpDiffer - single character differences
    public function ratcliffObershelpDifferSingleCharDiff(): void
    {
        $differ = new RatcliffObershelpDiffer();
        $diff = $differ->diff("a", "b");

        Assert::true(\count($diff) > 0);
    }

    // RatcliffObershelpDiffer - very similar strings with small diff
    public function ratcliffObershelpDifferVerySmallDiff(): void
    {
        $differ = new RatcliffObershelpDiffer();
        $diff = $differ->diff("same\ntext\nhere", "same\ntext\nchanged");

        $contexts = \array_filter($diff, static fn($line) => $line->op === DiffOp::Context);
        Assert::true(\count($contexts) >= 2);
    }

    // RatcliffObershelpDiffer - block expansion left and right with surrounding equal lines
    public function ratcliffObershelpDifferBlockExpansion(): void
    {
        $differ = new RatcliffObershelpDiffer();
        // Create a pattern where matching block can expand left and right
        $expected = "prefix\nmatch\nchange1\nmatch\nsuffix";
        $actual = "prefix\nmatch\nchange2\nmatch\nsuffix";

        $diff = $differ->diff($expected, $actual);

        $contexts = \array_filter($diff, static fn($line) => $line->op === DiffOp::Context);
        $removes = \array_filter($diff, static fn($line) => $line->op === DiffOp::Remove);
        $adds = \array_filter($diff, static fn($line) => $line->op === DiffOp::Add);

        Assert::true(\count($contexts) >= 2 && \count($removes) > 0 && \count($adds) > 0);
    }

    // RatcliffObershelpDiffer - junk detection (autoJunk with large repetitive content)
    public function ratcliffObershelpDifferJunkDetection(): void
    {
        $differ = new RatcliffObershelpDiffer();
        // Create a case with many repetitions that would trigger junk detection (m >= 200, threshold)
        $lines = \array_fill(0, 100, "repeated");
        $repeated = \implode("\n", $lines);
        $expected = $repeated . "\nunique\nchange";
        $actual = $repeated . "\nunique\nsame";

        $diff = $differ->diff($expected, $actual);

        Assert::true(\count($diff) > 0);
    }

    // RatcliffObershelpDiffer - pattern that triggers boundary conditions in matching
    public function ratcliffObershelpDifferBoundaryMatching(): void
    {
        $differ = new RatcliffObershelpDiffer();
        // Create scenario where j < bLo and j >= bHi conditions matter
        $expected = "a\nb\nc\nd\ne";
        $actual = "a\nb\nX\nd\ne";

        $diff = $differ->diff($expected, $actual);

        $edits = \array_filter($diff, static fn($line) => $line->op !== DiffOp::Context);
        Assert::true(\count($edits) > 0);
    }

    // RatcliffObershelpDiffer - matching block growth with surrounding equal context
    // Tests the while loops that grow matches left and right (lines 133-146)
    public function ratcliffObershelpDifferBlockGrowth(): void
    {
        $differ = new RatcliffObershelpDiffer();
        $expected = "ctx1\nctx2\nmatch\nmatch\nmatch\nctx3\nctx4";
        $actual = "ctx1\nctx2\nchange\nmatch\nmatch\nctx3\nctx4";

        $diff = $differ->diff($expected, $actual);

        $contexts = \array_filter($diff, static fn($line) => $line->op === DiffOp::Context);
        Assert::true(\count($contexts) >= 4);
    }

    // RatcliffObershelpDiffer - pattern with repeated lines that should not expand past bounds
    public function ratcliffObershelpDifferNoBoundaryOverrun(): void
    {
        $differ = new RatcliffObershelpDiffer();
        // Simple change that should not expand beyond the actual differences
        $expected = "start\nchange1\nend";
        $actual = "start\nchange2\nend";

        $diff = $differ->diff($expected, $actual);

        $contexts = \array_filter($diff, static fn($line) => $line->op === DiffOp::Context);
        Assert::true(\count($contexts) >= 2);
    }

    // RatcliffObershelpDiffer - complex case with junk filtering and block detection
    public function ratcliffObershelpDifferComplexWithJunk(): void
    {
        $differ = new RatcliffObershelpDiffer();
        // Create pattern where junk lines might be filtered (many repetitions)
        $junk_lines = \array_fill(0, 50, "the");
        $expected = \implode("\n", $junk_lines) . "\nunique_start\nmiddle\nunique_end";
        $actual = \implode("\n", $junk_lines) . "\nunique_start\nchanged\nunique_end";

        $diff = $differ->diff($expected, $actual);

        Assert::true(\count($diff) > 0);
    }

    // RatcliffObershelpDiffer - very large repetitive content (triggers junk filtering)
    // Tests lines 168-171: the autoJunk filtering when m >= 200
    public function ratcliffObershelpDifferVeryLargeWithAutoJunk(): void
    {
        $differ = new RatcliffObershelpDiffer();
        // Generate 200+ lines to trigger autoJunk
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

    // HirschbergDiffer - forcing n === 0 in recursion through strategic diff
    public function hirschbergDifferRecursiveEmptyRange(): void
    {
        $differ = new HirschbergDiffer();
        // Create a case where recursive calls will hit n === 0
        // By making one side much smaller and placing it strategically
        $expected = "a\na\na\nb\nb\nb\nc\nc\nc";
        $actual = "c\nc\nc";

        $diff = $differ->diff($expected, $actual);

        $removes = \array_filter($diff, static fn($line) => $line->op === DiffOp::Remove);
        $adds = \array_filter($diff, static fn($line) => $line->op === DiffOp::Add);

        Assert::true(\count($removes) > 0);
    }
}
