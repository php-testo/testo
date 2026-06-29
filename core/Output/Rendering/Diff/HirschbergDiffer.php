<?php

declare(strict_types=1);

namespace Testo\Output\Rendering\Diff;

/**
 * Linear-space LCS diff via Hirschberg's divide-and-conquer (Hirschberg 1975).
 *
 * Produces the same minimal, LCS-maximal alignment as {@see LcsDiffer} but in O(M) working memory
 * instead of the full O(N·M) table — it keeps only two DP rows and recursively splits on the column
 * that maximises the combined forward/backward LCS length. Time stays O(N·M), so this is the
 * memory-efficient counterpart to the plain LCS table (the variant `sebastian/diff` historically
 * shipped as its memory-efficient calculator), not a faster algorithm — for speed prefer
 * {@see MyersDiffer}.
 *
 * @internal
 */
final class HirschbergDiffer implements Differ
{
    #[\Override]
    public function diff(string $expected, string $actual): array
    {
        $a = \explode("\n", $expected);
        $b = \explode("\n", $actual);

        $out = [];
        self::diffRange($a, 0, \count($a), $b, 0, \count($b), $out);

        return $out;
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     * @param list<DiffLine> $out
     */
    private static function diffRange(array $a, int $aLo, int $aHi, array $b, int $bLo, int $bHi, array &$out): void
    {
        $n = $aHi - $aLo;
        $m = $bHi - $bLo;

        // Only the b-range can collapse to empty here: the entry point passes a non-empty a-range
        // (`explode()` yields at least one line) and the recursive split halves the a-range with
        // intdiv, so $n is always >= 1, whereas a chosen split column can empty one side's b-range.
        if ($m === 0) {
            for ($i = $aLo; $i < $aHi; $i++) {
                $out[] = new DiffLine(DiffOp::Remove, $a[$i]);
            }
            return;
        }
        if ($n === 1) {
            self::diffSingleRow($a[$aLo], $b, $bLo, $bHi, $out);
            return;
        }

        $aMid = \intdiv($aLo + $aHi, 2);

        // LCS length of the left a-half against each prefix of the b-range, and of the right
        // a-half against each suffix; the best split column maximises their sum.
        $scoreL = self::lcsLengths($a, $aLo, $aMid, $b, $bLo, $bHi, false);
        $scoreR = self::lcsLengths($a, $aMid, $aHi, $b, $bLo, $bHi, true);

        $bMid = $bLo;
        $best = -1;
        for ($k = 0; $k <= $m; $k++) {
            $kr = $m - $k;
            \assert($kr >= 0);
            $sum = ($scoreL[$k] ?? 0) + ($scoreR[$kr] ?? 0);
            if ($sum > $best) {
                $best = $sum;
                $bMid = $bLo + $k;
            }
        }

        self::diffRange($a, $aLo, $aMid, $b, $bLo, $bMid, $out);
        self::diffRange($a, $aMid, $aHi, $b, $bMid, $bHi, $out);
    }

    /**
     * Single a-line against a b-range: keep the first matching b-line as context (LCS is 1) with the
     * surrounding b-lines as additions, or remove the a-line and add the whole range (LCS is 0).
     *
     * @param list<string> $b
     * @param list<DiffLine> $out
     */
    private static function diffSingleRow(string $line, array $b, int $bLo, int $bHi, array &$out): void
    {
        $found = -1;
        for ($j = $bLo; $j < $bHi; $j++) {
            if ($b[$j] === $line) {
                $found = $j;
                break;
            }
        }

        if ($found === -1) {
            $out[] = new DiffLine(DiffOp::Remove, $line);
            for ($j = $bLo; $j < $bHi; $j++) {
                $out[] = new DiffLine(DiffOp::Add, $b[$j]);
            }
            return;
        }

        for ($j = $bLo; $j < $found; $j++) {
            $out[] = new DiffLine(DiffOp::Add, $b[$j]);
        }
        $out[] = new DiffLine(DiffOp::Context, $line);
        for ($j = $found + 1; $j < $bHi; $j++) {
            $out[] = new DiffLine(DiffOp::Add, $b[$j]);
        }
    }

    /**
     * LCS length of the a-range against every prefix of the b-range (or every suffix when $reverse),
     * computed with a single rolling row. Returns m+1 values indexed by the prefix/suffix length.
     *
     * @param list<string> $a
     * @param list<string> $b
     * @return array<int<0, max>, int>
     */
    private static function lcsLengths(array $a, int $aLo, int $aHi, array $b, int $bLo, int $bHi, bool $reverse): array
    {
        $n = $aHi - $aLo;
        $m = $bHi - $bLo;
        $prev = \array_fill(0, $m + 1, 0);

        for ($ii = 0; $ii < $n; $ii++) {
            $ia = $reverse ? $aHi - 1 - $ii : $aLo + $ii;
            \assert($ia >= 0);
            $ai = $a[$ia];
            $curr = \array_fill(0, $m + 1, 0);
            for ($jj = 1; $jj <= $m; $jj++) {
                $ib = $reverse ? $bHi - $jj : $bLo + $jj - 1;
                \assert($ib >= 0);
                $bj = $b[$ib];
                $curr[$jj] = $ai === $bj
                    ? $prev[$jj - 1] + 1
                    : \max($prev[$jj], $curr[$jj - 1]);
            }
            $prev = $curr;
        }

        return $prev;
    }
}
