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
use Testo\Test;

/**
 * Behavioural tests for the alternative {@see Differ} strategies. The minimal-edit guarantees and
 * the core invariants for {@see MyersDiffer}/{@see LcsDiffer} live in {@see DifferTest}; this file
 * covers the prefix/suffix decorator, patience and Hirschberg variants.
 */
#[Test]
#[Covers(PrefixSuffixDiffer::class)]
#[Covers(PatienceDiffer::class)]
#[Covers(HirschbergDiffer::class)]
final class DifferStrategiesTest
{
    /**
     * Every strategy — including patience, which does not minimise edits — must still emit a valid
     * edit script: dropping the other side's edits reconstructs each side verbatim.
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
     * The minimal strategies (memory-efficient LCS, and Myers behind the prefix/suffix decorator)
     * compute a shortest edit script, so their edit count must equal plain Myers'. Patience is
     * intentionally excluded — it optimises alignment, not edit count.
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
     * Patience anchors on lines that are unique on both sides. Here "x" repeats and cannot anchor,
     * but "ANCHOR" is unique and pins the alignment, so it survives as a single context line instead
     * of being torn into a remove/add pair the way a sliding minimal-edit diff might.
     */
    public function patienceKeepsAUniqueCommonLineAsContext(): void
    {
        $diff = (new PatienceDiffer())->diff("x\nANCHOR\nx", "x\nx\nANCHOR\nx\nx");

        Assert::same(self::countOp($diff, DiffOp::Context, 'ANCHOR'), 1);
        Assert::same(self::countOp($diff, DiffOp::Remove, 'ANCHOR'), 0);
    }

    /**
     * With no line unique to both sides patience has nothing to anchor on and defers to its fallback
     * differ; trimming the shared prefix/suffix first leaves the edit count identical to plain Myers.
     */
    public function patienceFallsBackToMyersEditCountWithoutUniqueLines(): void
    {
        $expected = "a\na\nb";
        $actual = "a\nb\nb";

        $patience = (new PatienceDiffer(new MyersDiffer()))->diff($expected, $actual);
        $myers = (new MyersDiffer())->diff($expected, $actual);

        Assert::same(self::editCount($patience), self::editCount($myers));
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
     * @param list<DiffLine> $diff
     * @return int<0, max>
     */
    private static function countOp(array $diff, DiffOp $op, string $line): int
    {
        $count = 0;
        foreach ($diff as $entry) {
            ($entry->op === $op && $entry->line === $line) and $count++;
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
