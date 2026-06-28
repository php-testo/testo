<?php

declare(strict_types=1);

namespace Tests\Output\Bench;

use Testo\Bench;
use Testo\Output\Rendering\Diff\LcsDiffer;
use Testo\Output\Rendering\Diff\MyersDiffer;

/**
 * Head-to-head comparison of the two {@see \Testo\Output\Rendering\Diff\Differ} implementations.
 *
 * The marked method is the production default ({@see MyersDiffer}, alias `current`); it is compared
 * against the legacy {@see LcsDiffer}. Both build the same input pair per call, so the shared O(N)
 * setup cancels out of the comparison and the gap reflects the diff algorithm itself.
 *
 * Empirically (PHP 8.4, ~few differing lines) the LCS table degrades quadratically while Myers stays
 * near-linear: at 1000 lines Myers is ~100× faster and uses a fraction of the memory; at 3000 lines
 * LCS already needs ~200 MB and ~2 s, Myers ~16 MB and ~13 ms.
 */
final class DiffBench
{
    #[Bench(
        ['lcs' => [self::class, 'lcs']],
        arguments: [200, 8],
        warmup: 2,
        calls: 5,
        iterations: 5,
    )]
    public static function myers(int $lines, int $changes): void
    {
        [$expected, $actual] = self::pair($lines, $changes);

        (new MyersDiffer())->diff($expected, $actual);
    }

    public static function lcs(int $lines, int $changes): void
    {
        [$expected, $actual] = self::pair($lines, $changes);

        (new LcsDiffer())->diff($expected, $actual);
    }

    /**
     * Two nearly-identical multi-line blobs: `$lines` lines with `$changes` of them altered, evenly
     * spread — the realistic shape of an assertion-failure comparison.
     *
     * @return array{string, string}
     */
    private static function pair(int $lines, int $changes): array
    {
        $a = [];
        $b = [];
        for ($i = 0; $i < $lines; $i++) {
            $a[$i] = $b[$i] = "line {$i} content here";
        }
        for ($c = 0; $c < $changes && $c < $lines; $c++) {
            $idx = (int) ($c * ($lines / \max(1, $changes)));
            $b[$idx] = "CHANGED {$idx}";
        }

        return [\implode("\n", $a), \implode("\n", $b)];
    }
}
