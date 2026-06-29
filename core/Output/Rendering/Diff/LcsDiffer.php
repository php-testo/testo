<?php

declare(strict_types=1);

namespace Testo\Output\Rendering\Diff;

/**
 * Line diff via a full Longest Common Subsequence dynamic-programming table.
 *
 * This is Testo's original differ, kept for reference and benchmarking against {@see MyersDiffer}.
 * It builds the complete (N + 1) × (M + 1) table, so it costs O(N · M) time and memory and degrades
 * sharply on large inputs; prefer {@see MyersDiffer} in production code.
 *
 * @internal
 */
final class LcsDiffer implements Differ
{
    #[\Override]
    public function diff(string $expected, string $actual): array
    {
        $a = \explode("\n", $expected);
        $b = \explode("\n", $actual);

        $m = \count($a);
        $n = \count($b);
        $lcs = \array_fill(0, $m + 1, \array_fill(0, $n + 1, 0));

        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                $lcs[$i][$j] = $a[$i - 1] === $b[$j - 1]
                    ? $lcs[$i - 1][$j - 1] + 1
                    : \max($lcs[$i - 1][$j], $lcs[$i][$j - 1]);
            }
        }

        $diff = [];
        $i = $m;
        $j = $n;
        while ($i > 0 && $j > 0) {
            if ($a[$i - 1] === $b[$j - 1]) {
                \array_unshift($diff, new DiffLine(DiffOp::Context, $a[$i - 1]));
                $i--;
                $j--;
            } elseif ($lcs[$i - 1][$j] >= $lcs[$i][$j - 1]) {
                \array_unshift($diff, new DiffLine(DiffOp::Remove, $a[$i - 1]));
                $i--;
            } else {
                \array_unshift($diff, new DiffLine(DiffOp::Add, $b[$j - 1]));
                $j--;
            }
        }
        while ($i > 0) {
            \array_unshift($diff, new DiffLine(DiffOp::Remove, $a[$i - 1]));
            $i--;
        }
        while ($j > 0) {
            \array_unshift($diff, new DiffLine(DiffOp::Add, $b[$j - 1]));
            $j--;
        }

        return $diff;
    }
}
