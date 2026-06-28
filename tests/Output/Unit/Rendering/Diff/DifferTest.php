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
use Testo\Output\Rendering\Diff\LcsDiffer;
use Testo\Output\Rendering\Diff\MyersDiffer;
use Testo\Test;

#[Test]
#[Covers(MyersDiffer::class)]
#[Covers(LcsDiffer::class)]
#[Covers(DiffLine::class)]
final class DifferTest
{
    /**
     * The core invariant every differ must hold: keeping only the lines that exist on a side (context
     * plus that side's edits) must reconstruct that side verbatim. Checked for both implementations.
     */
    #[DataCross(
        new DataProvider('differs'),
        new DataProvider('scenarios'),
    )]
    public function reconstructsBothSides(Differ $differ, string $expected, string $actual): void
    {
        $diff = $differ->diff($expected, $actual);

        Assert::same(self::reconstruct($diff, DiffOp::Add), $expected);
        Assert::same(self::reconstruct($diff, DiffOp::Remove), $actual);
    }

    /**
     * Both algorithms compute a minimal edit script, so they must report the same number of edits
     * (the order or tie-breaking may differ, the count may not).
     */
    #[DataProvider('scenarios')]
    public function myersAndLcsAgreeOnEditCount(string $expected, string $actual): void
    {
        $myers = (new MyersDiffer())->diff($expected, $actual);
        $lcs = (new LcsDiffer())->diff($expected, $actual);

        Assert::same(self::editCount($myers), self::editCount($lcs));
    }

    public function identicalInputProducesOnlyContext(): void
    {
        $diff = (new MyersDiffer())->diff("a\nb\nc", "a\nb\nc");

        Assert::count($diff, 3);
        foreach ($diff as $line) {
            Assert::same($line->op, DiffOp::Context);
        }
    }

    /**
     * The whole point of switching to Myers: a large, nearly identical input that the O(N·M) LCS
     * table would choke on must diff in O((N+M)·D) — here D is 1, so the script is a single
     * remove/add pair and the rest stays context.
     */
    public function myersScalesToLargeNearlyIdenticalInput(): void
    {
        $expected = \implode("\n", \array_map(static fn(int $i): string => "line {$i}", \range(1, 5000)));
        $actual = \str_replace('line 2500', 'line CHANGED', $expected);

        $diff = (new MyersDiffer())->diff($expected, $actual);

        Assert::same(self::editCount($diff), 2);
        Assert::same(self::reconstruct($diff, DiffOp::Add), $expected);
        Assert::same(self::reconstruct($diff, DiffOp::Remove), $actual);
    }

    /**
     * LcsDiffer must not access undefined array keys during its DP table traversal.
     * The base-row index must be 0, not 1; accessing key 0 on a table that starts at 1
     * would emit PHP warnings for "Undefined array key 0" and "Trying to access array
     * offset on null", which this test surfaces by promoting warnings to exceptions.
     */
    public function lcsEmitsNoWarnings(): void
    {
        $previous = \set_error_handler(
            static fn(int $errno, string $errstr): never => throw new \ErrorException($errstr, $errno),
            \E_WARNING | \E_NOTICE,
        );
        try {
            $diff = (new LcsDiffer())->diff("a\nb\nc", "a\nX\nc");
            Assert::count($diff, 4);
        } finally {
            \set_error_handler($previous);
        }
    }

    public static function differs(): iterable
    {
        yield 'myers' => [new MyersDiffer()];
        yield 'lcs' => [new LcsDiffer()];
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
    }

    /**
     * Reconstruct one side of the comparison by dropping the edits that belong to the other side.
     * Excluding {@see DiffOp::Add} leaves the expected side; excluding {@see DiffOp::Remove} the actual.
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
}
