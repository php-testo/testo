<?php

declare(strict_types=1);

namespace Testo\Output\Rendering\Diff;

/**
 * Ratcliff/Obershelp ("gestalt pattern matching") line diff, following the logic of Python's
 * `difflib.SequenceMatcher`.
 *
 * Instead of minimising edits it repeatedly locates the longest contiguous matching block and
 * recurses into the regions to its left and right. The result favours *readable* alignment over a
 * shortest edit script — the same niche as {@see PatienceDiffer}, by a different route. It does not
 * guarantee a minimal edit count, and its worst case is quadratic, so for large near-identical
 * inputs {@see MyersDiffer} is faster; this is here mainly to compare alignment quality.
 *
 * `findLongestMatch` is backed by a `b2j` index (line -> its positions in the new sequence). The
 * difflib "autojunk" heuristic drops over-popular lines from that index on large inputs so a line
 * repeated everywhere cannot dominate the matching; dropped lines still appear in the output, just
 * not as anchors.
 *
 * @internal
 */
final class RatcliffObershelpDiffer implements Differ
{
    public function __construct(
        private readonly bool $autoJunk = true,
    ) {}

    #[\Override]
    public function diff(string $expected, string $actual): array
    {
        $a = \explode("\n", $expected);
        $b = \explode("\n", $actual);
        $n = \count($a);
        $m = \count($b);

        $b2j = $this->buildB2J($b, $m);

        // Find all matching blocks by recursively splitting around the longest match. An explicit
        // stack avoids recursion; each frame is a [aLo, aHi, bLo, bHi] window.
        /** @var list<array{int<0, max>, int<0, max>, int<0, max>, int<0, max>}> $stack */
        $stack = [[0, $n, 0, $m]];
        /** @var list<array{int<0, max>, int<0, max>, int<0, max>}> $blocks */
        $blocks = [];
        while (($frame = \array_pop($stack)) !== null) {
            [$aLo, $aHi, $bLo, $bHi] = $frame;
            [$i, $j, $k] = self::findLongestMatch($a, $b, $b2j, $aLo, $aHi, $bLo, $bHi);
            if ($k <= 0) {
                continue;
            }

            $blocks[] = [$i, $j, $k];
            if ($aLo < $i && $bLo < $j) {
                $stack[] = [$aLo, $i, $bLo, $j];
            }
            if ($i + $k < $aHi && $j + $k < $bHi) {
                $stack[] = [$i + $k, $aHi, $j + $k, $bHi];
            }
        }

        \usort($blocks, static fn(array $x, array $y): int => $x[0] <=> $y[0] ?: $x[1] <=> $y[1]);

        // Walk the ordered blocks; the gap before each block is the edit (removes then adds), the
        // block itself is context. A trailing sentinel flushes the final gap.
        $out = [];
        $ai = 0;
        $bj = 0;
        $blocks[] = [$n, $m, 0];
        foreach ($blocks as [$i, $j, $k]) {
            for ($x = $ai; $x < $i; $x++) {
                $out[] = new DiffLine(DiffOp::Remove, $a[$x]);
            }
            for ($y = $bj; $y < $j; $y++) {
                $out[] = new DiffLine(DiffOp::Add, $b[$y]);
            }
            for ($t = 0; $t < $k; $t++) {
                $out[] = new DiffLine(DiffOp::Context, $a[$i + $t]);
            }
            $ai = $i + $k;
            $bj = $j + $k;
        }

        return $out;
    }

    /**
     * Longest matching block within a[aLo, aHi) × b[bLo, bHi): the run of equal lines that is
     * longest, then earliest. Returns [aStart, bStart, length].
     *
     * @param list<string> $a
     * @param list<string> $b
     * @param array<array-key, list<int<0, max>>> $b2j
     * @param int<0, max> $aLo
     * @param int<0, max> $aHi
     * @param int<0, max> $bLo
     * @param int<0, max> $bHi
     * @return array{int<0, max>, int<0, max>, int<0, max>}
     */
    private static function findLongestMatch(array $a, array $b, array $b2j, int $aLo, int $aHi, int $bLo, int $bHi): array
    {
        $bestI = $aLo;
        $bestJ = $bLo;
        $bestSize = 0;

        // j2len[j] = length of the longest matching run ending at a[i], b[j] for the previous i.
        /** @var array<int, int<0, max>> $j2len */
        $j2len = [];
        for ($i = $aLo; $i < $aHi; $i++) {
            /** @var array<int, int<0, max>> $next */
            $next = [];
            foreach ($b2j[$a[$i]] ?? [] as $j) {
                if ($j < $bLo) {
                    continue;
                }
                if ($j >= $bHi) {
                    break;
                }
                $k = ($j2len[$j - 1] ?? 0) + 1;
                $next[$j] = $k;
                if ($k > $bestSize) {
                    $bestI = $i - $k + 1;
                    $bestJ = $j - $k + 1;
                    $bestSize = $k;
                }
            }
            $j2len = $next;
        }

        // Grow the block over any equal lines on either side (popular lines are absent from b2j but
        // can still extend an existing block here).
        /** @psalm-suppress InvalidArrayOffset The loop only runs while $bestI > $aLo >= 0, so $bestI - 1 >= 0. */
        while ($bestI > $aLo && $bestJ > $bLo && $a[$bestI - 1] === $b[$bestJ - 1]) {
            --$bestI;
            --$bestJ;
            ++$bestSize;
        }

        // The backward walk stops at $aLo/$bLo (>= 0); restate that for the analyzer so the forward
        // offset arithmetic and the return type check out without per-iteration guards (no-op).
        $bestI < 0 and $bestI = 0;
        $bestJ < 0 and $bestJ = 0;

        while ($bestI + $bestSize < $aHi && $bestJ + $bestSize < $bHi && $a[$bestI + $bestSize] === $b[$bestJ + $bestSize]) {
            ++$bestSize;
        }

        return [$bestI, $bestJ, $bestSize];
    }

    /**
     * Build the line -> positions index for the new sequence, applying difflib's autojunk rule:
     * on sequences of 200+ lines, lines occurring in more than ~1% of positions are dropped as
     * anchors so a ubiquitous line cannot swamp the matching.
     *
     * @param list<string> $b
     * @return array<array-key, list<int<0, max>>>
     */
    private function buildB2J(array $b, int $m): array
    {
        /** @var array<array-key, list<int<0, max>>> $b2j */
        $b2j = [];
        foreach ($b as $j => $line) {
            $b2j[$line][] = $j;
        }

        if ($this->autoJunk && $m >= 200) {
            $threshold = \intdiv($m, 100) + 1;
            foreach ($b2j as $line => $positions) {
                if (\count($positions) > $threshold) {
                    unset($b2j[$line]);
                }
            }
        }

        return $b2j;
    }
}
