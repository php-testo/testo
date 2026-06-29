<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Rendering\Diff;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataCross;
use Testo\Data\DataProvider;
use Testo\Output\Rendering\Diff\DiffLine;
use Testo\Output\Rendering\Diff\Differ;
use Testo\Output\Rendering\Diff\DiffOp;
use Testo\Output\Rendering\Diff\HirschbergDiffer;
use Testo\Output\Rendering\Diff\LcsDiffer;
use Testo\Output\Rendering\Diff\MyersDiffer;
use Testo\Output\Rendering\Diff\PatienceDiffer;
use Testo\Output\Rendering\Diff\PrefixSuffixDiffer;
use Testo\Output\Rendering\Diff\RatcliffObershelpDiffer;
use Testo\Test;
use Tests\Output\Stub\Diff\RecordingDiffer;

/**
 * Behavioural tests for the alternative {@see Differ} strategies — the prefix/suffix decorator,
 * patience, Hirschberg and Ratcliff/Obershelp. The minimal-edit guarantees and the core invariants
 * for {@see MyersDiffer}/{@see LcsDiffer} live in {@see DifferTest}.
 *
 * The reconstruction invariant alone is deliberately weak (a "delete everything, add everything"
 * differ would satisfy it), so the quality-oriented strategies are additionally pinned with exact
 * alignment snapshots and delegation spies, and the minimal ones with an edit-count parity check.
 */
#[Test]
#[Covers(PrefixSuffixDiffer::class)]
#[Covers(PatienceDiffer::class)]
#[Covers(HirschbergDiffer::class)]
#[Covers(RatcliffObershelpDiffer::class)]
final class DifferStrategiesTest
{
    /**
     * Every strategy — including patience and Ratcliff/Obershelp, which do not minimise edits — must
     * still emit a valid edit script: dropping the other side's edits reconstructs each side verbatim.
     */
    #[DataCross(
        new DataProvider('strategies'),
        new DataProvider('scenarios'),
    )]
    public function reconstructsBothSides(Differ $differ, string $expected, string $actual): void
    {
        $diff = $differ->diff($expected, $actual);

        Assert::same(self::reconstruct($diff, DiffOp::Add), $expected);
        Assert::same(self::reconstruct($diff, DiffOp::Remove), $actual);
    }

    /**
     * Reconstruction must also hold on a large input that exercises the recursive/stack-based paths
     * (Hirschberg's divide-and-conquer, patience recursion, the Ratcliff/Obershelp stack) — not just
     * the tiny hand-written scenarios above.
     */
    #[DataProvider('strategies')]
    public function reconstructsLargeInput(Differ $differ): void
    {
        $base = \array_map(static fn(int $i): string => "line {$i}", \range(1, 300));
        $expected = \implode("\n", $base);
        $base[150] = 'line CHANGED';
        \array_splice($base, 80, 0, ['inserted']);
        $actual = \implode("\n", $base);

        $diff = $differ->diff($expected, $actual);

        Assert::same(self::reconstruct($diff, DiffOp::Add), $expected);
        Assert::same(self::reconstruct($diff, DiffOp::Remove), $actual);
    }

    /**
     * The minimal strategies (memory-efficient LCS, and Myers behind the prefix/suffix decorator)
     * compute a shortest edit script, so their edit count must equal plain Myers'. Patience and
     * Ratcliff/Obershelp are intentionally excluded — they optimise alignment, not edit count.
     */
    #[DataCross(
        new DataProvider('minimalStrategies'),
        new DataProvider('scenarios'),
    )]
    public function minimalStrategiesMatchMyersEditCount(Differ $differ, string $expected, string $actual): void
    {
        $reference = (new MyersDiffer())->diff($expected, $actual);

        Assert::same(self::editCount($differ->diff($expected, $actual)), self::editCount($reference));
    }

    /**
     * Patience anchors on lines unique to both sides. "x" repeats and cannot anchor, but "ANCHOR" is
     * unique and pins the alignment: it stays a single context line while the duplicated "x"s around
     * it are paired as additions. The exact script is asserted so a regression in alignment is caught
     * (reconstruction alone would not notice).
     */
    public function patienceProducesAnchoredAlignment(): void
    {
        $diff = (new PatienceDiffer())->diff("x\nANCHOR\nx", "x\nx\nANCHOR\nx\nx");

        Assert::same(self::render($diff), ['  x', '+ x', '  ANCHOR', '+ x', '  x']);
    }

    /**
     * Ratcliff/Obershelp anchors on the single longest matching block. On a reversal it keeps only
     * the longest run ("a" here) as context and rewrites the rest, which is its defining behaviour.
     */
    public function ratcliffAnchorsOnLongestBlock(): void
    {
        $diff = (new RatcliffObershelpDiffer())->diff("a\nb\nc", "c\nb\na");

        Assert::same(self::render($diff), ['+ c', '+ b', '  a', '- b', '- c']);
    }

    /**
     * With no line unique to both sides patience has nothing to anchor on and must defer to its
     * fallback differ — verified directly via a recording spy, not merely inferred from the result.
     */
    public function patienceDelegatesToFallbackWithoutAnchor(): void
    {
        $spy = new RecordingDiffer(new MyersDiffer());

        $diff = (new PatienceDiffer($spy))->diff("a\na\nb", "a\nb\nb");

        Assert::count($spy->calls, 1);
        Assert::same(self::reconstruct($diff, DiffOp::Add), "a\na\nb");
        Assert::same(self::reconstruct($diff, DiffOp::Remove), "a\nb\nb");
    }

    /**
     * The reverse: when every line already matches, the shared prefix/suffix trimming consumes the
     * whole input and the fallback is never touched.
     */
    public function patienceSkipsFallbackForIdenticalInput(): void
    {
        $spy = new RecordingDiffer(new MyersDiffer());

        (new PatienceDiffer($spy))->diff("a\nb\nc", "a\nb\nc");

        Assert::count($spy->calls, 0);
    }

    /**
     * The decorator must trim the shared head/tail itself and hand only the differing middle to its
     * inner differ — confirmed by inspecting exactly what the spy received.
     */
    public function prefixSuffixPassesOnlyTheMiddleToItsInner(): void
    {
        $spy = new RecordingDiffer(new MyersDiffer());

        $diff = (new PrefixSuffixDiffer($spy))->diff("head\nA\ntail", "head\nB\ntail");

        Assert::count($spy->calls, 1);
        Assert::same($spy->calls[0], ['A', 'B']);
        Assert::same(self::reconstruct($diff, DiffOp::Add), "head\nA\ntail");
        Assert::same(self::reconstruct($diff, DiffOp::Remove), "head\nB\ntail");
    }

    /**
     * When the shared suffix runs all the way back to a-index 0 (here the whole of `a`, "x", is the
     * shared tail of "y\nx"), the trimmed middle is one-sided — `midA = []`, `midB = ["y"]` — and the
     * decorator emits it directly without ever consulting its inner differ. A guard that refuses to
     * include index 0 in the suffix would leave a two-sided middle and delegate, so the spy must record
     * zero calls.
     */
    public function prefixSuffixIncludesAIndexZeroInSharedSuffix(): void
    {
        $spy = new RecordingDiffer(new MyersDiffer());

        $diff = (new PrefixSuffixDiffer($spy))->diff("x", "y\nx");

        Assert::count($spy->calls, 0);
        Assert::same(self::render($diff), ['+ y', '  x']);
    }

    /**
     * Mirror of the a-index case for the b side: when the shared suffix runs all the way back to
     * b-index 0 (here the whole of `b`, "x", is the shared tail of "y\nx"), the trimmed middle is
     * one-sided — `midA = ["y"]`, `midB = []` — and the decorator emits it directly without ever
     * consulting its inner differ. A guard that refuses to include b-index 0 in the suffix would
     * leave a two-sided middle and delegate, so the spy must record zero calls.
     */
    public function prefixSuffixIncludesBIndexZeroInSharedSuffix(): void
    {
        $spy = new RecordingDiffer(new MyersDiffer());

        $diff = (new PrefixSuffixDiffer($spy))->diff("y\nx", "x");

        Assert::count($spy->calls, 0);
        Assert::same(self::render($diff), ['- y', '  x']);
    }

    /**
     * On fully identical input the prefix scan must stop exactly at the shared length and never probe
     * one index past the end of either side. A bound that reads `$a[$max]`/`$b[$max]` would raise an
     * "Undefined array key" warning (the slices happen to collapse to the same context lines, so only
     * the warning betrays it). Promote any warning to an exception so the off-by-one is caught, and pin
     * the exact emitted script: three context lines, nothing else.
     */
    public function prefixSuffixDoesNotReadPastTheSharedLength(): void
    {
        \set_error_handler(static function (int $severity, string $message): bool {
            throw new \RuntimeException("Unexpected PHP warning: {$message}");
        });

        try {
            $diff = (new PrefixSuffixDiffer())->diff("a\nb\nc", "a\nb\nc");
        } finally {
            \restore_error_handler();
        }

        Assert::same(self::render($diff), ['  a', '  b', '  c']);
    }

    /**
     * On large inputs Ratcliff/Obershelp's autojunk heuristic drops over-popular lines from the
     * anchor index. With the heuristic on or off the result must stay a valid edit script — here a
     * delimiter line repeats far above the threshold across 260 lines.
     */
    public function ratcliffStaysCorrectWithPopularLines(): void
    {
        $lines = [];
        for ($i = 0; $i < 130; $i++) {
            $lines[] = "item {$i}";
            $lines[] = '---';
        }
        $expected = \implode("\n", $lines);
        $lines[60] = 'item CHANGED';
        $actual = \implode("\n", $lines);

        foreach ([true, false] as $autoJunk) {
            $diff = (new RatcliffObershelpDiffer($autoJunk))->diff($expected, $actual);

            Assert::same(self::reconstruct($diff, DiffOp::Add), $expected);
            Assert::same(self::reconstruct($diff, DiffOp::Remove), $actual);
        }
    }

    /**
     * Hirschberg yields the same minimal LCS result as the plain table but keeps only two rolling
     * rows, so its peak allocation is a fraction of the O(N·M) table on a large input.
     */
    public function hirschbergUsesFarLessMemoryThanTheLcsTable(): void
    {
        $expected = \implode("\n", \array_map(static fn(int $i): string => "row {$i}", \range(1, 400)));
        $actual = \str_replace('row 200', 'row CHANGED', $expected);

        $tableBytes = self::peakBytes(static fn(): array => (new LcsDiffer())->diff($expected, $actual));
        $rowBytes = self::peakBytes(static fn(): array => (new HirschbergDiffer())->diff($expected, $actual));

        // The real gap is an order of magnitude; 4x keeps the assertion robust across PHP builds.
        Assert::true($rowBytes * 4 < $tableBytes);
    }

    public static function strategies(): iterable
    {
        yield 'hirschberg' => [new HirschbergDiffer()];
        yield 'prefix-suffix' => [new PrefixSuffixDiffer()];
        yield 'patience' => [new PatienceDiffer()];
        yield 'ratcliff-obershelp' => [new RatcliffObershelpDiffer()];
    }

    public static function minimalStrategies(): iterable
    {
        yield 'hirschberg' => [new HirschbergDiffer()];
        yield 'prefix-suffix' => [new PrefixSuffixDiffer()];
    }

    public static function scenarios(): iterable
    {
        yield 'identical' => ["a\nb\nc", "a\nb\nc"];
        yield 'single change' => ["a\nb\nc", "a\nX\nc"];
        yield 'append' => ["a\nb", "a\nb\nc"];
        yield 'prepend' => ["b\nc", "a\nb\nc"];
        yield 'remove middle' => ["a\nb\nc", "a\nc"];
        yield 'all different' => ["a\nb", "x\ny"];
        yield 'empty expected' => ['', "a\nb"];
        yield 'empty actual' => ["a\nb", ''];
        yield 'both empty' => ['', ''];
        yield 'duplicate lines' => ["a\na\na", "a\na"];
        yield 'reorder' => ["a\nb\nc", "c\nb\na"];
        yield 'repeated delimiters' => ["{\na\n}\n{\nb\n}", "{\nb\n}\n{\na\n}"];
        yield 'match only in left a-half boundary' => ["x\nz", "x"];
    }

    /**
     * Reconstruct one side by dropping the edits that belong to the other: excluding {@see DiffOp::Add}
     * leaves the expected side, excluding {@see DiffOp::Remove} the actual.
     *
     * @param list<DiffLine> $diff
     */
    private static function reconstruct(array $diff, DiffOp $exclude): string
    {
        $lines = [];
        foreach ($diff as $line) {
            $line->op === $exclude or $lines[] = $line->line;
        }

        return \implode("\n", $lines);
    }

    /**
     * Render a diff as one prefixed string per line (`  ` context, `- ` remove, `+ ` add) for exact,
     * readable alignment snapshots.
     *
     * @param list<DiffLine> $diff
     * @return list<string>
     */
    private static function render(array $diff): array
    {
        return \array_map(
            static fn(DiffLine $line): string => match ($line->op) {
                DiffOp::Context => "  {$line->line}",
                DiffOp::Remove => "- {$line->line}",
                DiffOp::Add => "+ {$line->line}",
            },
            $diff,
        );
    }

    /**
     * @param list<DiffLine> $diff
     * @return int<0, max>
     */
    private static function editCount(array $diff): int
    {
        $count = 0;
        foreach ($diff as $line) {
            $line->op === DiffOp::Context or $count++;
        }

        return $count;
    }

    /**
     * Peak bytes allocated while running $fn, measured from a freshly reset peak.
     *
     * @param callable(): list<DiffLine> $fn
     * @return int<0, max>
     */
    private static function peakBytes(callable $fn): int
    {
        \gc_collect_cycles();
        \memory_reset_peak_usage();
        $baseline = \memory_get_peak_usage();
        $fn();

        return \max(0, \memory_get_peak_usage() - $baseline);
    }
}
