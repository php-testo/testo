<?php

declare(strict_types=1);

namespace Testo\Output\Rendering\Diff;

/**
 * Line diff based on Eugene W. Myers' greedy O(ND) algorithm (Myers 1986, "An O(ND) Difference
 * Algorithm and Its Variations").
 *
 * Runs in O((N + M) · D) time, where D is the edit distance, so for the common case of large inputs
 * with few differences it is dramatically faster than a full O(N · M) LCS table (see {@see LcsDiffer}).
 * It records the search front for every d to rebuild the script, so its extra memory is O(D²) — tiny
 * when D is small, but note this is the greedy variant, not Myers' linear-space §4b refinement; for
 * the memory axis use {@see HirschbergDiffer}. The script is reconstructed by walking the recorded
 * traces backwards and reversing once, avoiding the quadratic `array_unshift` cascade.
 *
 * @internal
 */
final class MyersDiffer implements Differ
{
    #[\Override]
    public function diff(string $expected, string $actual): array
    {
        $a = \explode("\n", $expected);
        $b = \explode("\n", $actual);
        $n = \count($a);
        $m = \count($b);
        $max = $n + $m;

        // For each edit distance d, record the furthest-reaching x on every diagonal k.
        $trace = [];
        $v = [1 => 0];
        $found = 0;
        for ($d = 0; $d <= $max; $d++) {
            $trace[$d] = $v;
            for ($k = -$d; $k <= $d; $k += 2) {
                // Pick the better neighbouring diagonal to extend from: down (insert) or right (delete).
                $x = $k === -$d || ($k !== $d && ($v[$k - 1] ?? 0) < ($v[$k + 1] ?? 0))
                    ? ($v[$k + 1] ?? 0)
                    : ($v[$k - 1] ?? 0) + 1;
                $y = $x - $k;

                // Follow the diagonal (a "snake") across all matching lines for free.
                while ($x < $n && $y < $m && $a[$x] === $b[$y]) {
                    $x++;
                    $y++;
                }

                $v[$k] = $x;
                if ($x >= $n && $y >= $m) {
                    $found = $d;
                    break 2;
                }
            }
        }

        // Walk the traces backwards. The snake/edit ordering yields a reversed script we flip once.
        $reversed = [];
        $x = $n;
        $y = $m;
        for ($d = $found; $d > 0; $d--) {
            $v = $trace[$d];
            $k = $x - $y;
            $prevK = $k === -$d || ($k !== $d && ($v[$k - 1] ?? 0) < ($v[$k + 1] ?? 0))
                ? $k + 1
                : $k - 1;
            $prevX = $v[$prevK] ?? 0;
            $prevY = $prevX - $prevK;

            // The asserts below restate Myers' invariant — every coordinate touched here is a
            // reachable, in-bounds point — so the $x-1 / $y-1 line accesses are provably safe.
            while ($x > $prevX && $y > $prevY) {
                \assert($x >= 1);
                $reversed[] = new DiffLine(DiffOp::Context, $a[$x - 1]);
                $x--;
                $y--;
            }

            if ($x === $prevX) {
                \assert($y >= 1);
                $reversed[] = new DiffLine(DiffOp::Add, $b[$y - 1]);
                $y--;
            } else {
                \assert($x >= 1);
                $reversed[] = new DiffLine(DiffOp::Remove, $a[$x - 1]);
                $x--;
            }
        }

        // Leading run of common lines reached at d == 0.
        while ($x > 0 && $y > 0) {
            $reversed[] = new DiffLine(DiffOp::Context, $a[$x - 1]);
            $x--;
            $y--;
        }

        return \array_reverse($reversed);
    }
}
