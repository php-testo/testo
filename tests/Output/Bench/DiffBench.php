<?php

declare(strict_types=1);

namespace Tests\Output\Bench;

use Testo\Bench;
use Testo\Output\Rendering\Diff\HirschbergDiffer;
use Testo\Output\Rendering\Diff\LcsDiffer;
use Testo\Output\Rendering\Diff\MyersDiffer;
use Testo\Output\Rendering\Diff\PatienceDiffer;
use Testo\Output\Rendering\Diff\PrefixSuffixDiffer;
use Testo\Output\Rendering\Diff\RatcliffObershelpDiffer;

/**
 * Head-to-head comparison of the {@see \Testo\Output\Rendering\Diff\Differ} implementations.
 *
 * The marked method is the production default ({@see MyersDiffer}, alias `current`); it is compared
 * against the legacy {@see LcsDiffer}, the memory-efficient {@see HirschbergDiffer}, {@see PatienceDiffer},
 * and {@see MyersDiffer} behind the {@see PrefixSuffixDiffer} decorator. Every callable builds the same
 * input pair per call, so the shared O(N) setup cancels out and the gap reflects the algorithm itself.
 *
 * Empirically (PHP 8.4, ~few differing lines) the LCS table degrades quadratically while Myers stays
 * near-linear: at 1000 lines Myers is ~100× faster and uses a fraction of the memory; at 3000 lines
 * LCS already needs ~200 MB and ~2 s, Myers ~16 MB and ~13 ms. Hirschberg matches LCS in time but in
 * linear memory. The prefix/suffix decorator only wins when the changes are localised (a shared head
 * and tail to trim); with the evenly-spread changes this benchmark generates it tracks plain Myers.
 */
final class DiffBench
{
    #[Bench(
        [
            'lcs' => [self::class, 'lcs'],
            'hirschberg' => [self::class, 'hirschberg'],
            'patience' => [self::class, 'patience'],
            'prefix-suffix' => [self::class, 'prefixSuffix'],
            'ratcliff' => [self::class, 'ratcliff'],
        ],
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

    public static function hirschberg(int $lines, int $changes): void
    {
        [$expected, $actual] = self::pair($lines, $changes);

        (new HirschbergDiffer())->diff($expected, $actual);
    }

    public static function patience(int $lines, int $changes): void
    {
        [$expected, $actual] = self::pair($lines, $changes);

        (new PatienceDiffer())->diff($expected, $actual);
    }

    public static function prefixSuffix(int $lines, int $changes): void
    {
        [$expected, $actual] = self::pair($lines, $changes);

        (new PrefixSuffixDiffer())->diff($expected, $actual);
    }

    public static function ratcliff(int $lines, int $changes): void
    {
        [$expected, $actual] = self::pair($lines, $changes);

        (new RatcliffObershelpDiffer())->diff($expected, $actual);
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
