<?php

declare(strict_types=1);

namespace Tests\Bench\Self;

use Testo\Bench\BenchWith;
use Testo\Inline\TestInline;

final class BenchWithAttr
{
    #[BenchWith(
        [
            // 'div' => [self::class, 'sumLinearF1'],
            'shift' => [self::class, 'sumLinearF2'],
            'multi' => [self::class, 'sumLinearF3'],
            // 'sumInCycle' => [self::class, 'sumInCycle'],
            // 'sumInArray' => [self::class, 'sumInArray'],
            // 'sumRange' => [self::class, 'sumRange'],
        ],
        arguments: [1, 20_000],
        revolutions: 2_000_000,
        iterations: 10,
    )]
    // #[TestInline([1, 2000], 2001000)]
    // #[TestInline([24, 2000], 2000724)]
    public static function sumLinearF1(int $a, int $b): int
    {
        $d = $b - $a + 1;
        return (int) (($d - 1) * $d / 2) + $a * $d;
    }

    public static function sumLinearF3(int $a, int $b): int
    {
        $d = $b - $a + 1;
        return (int) (($d - 1) * $d * 0.5) + $a * $d;
    }

    // #[TestInline([1, 2000], 2001000)]
    // #[TestInline([24, 2000], 2000724)]
    // #[BenchWith(
    //     [
    //         'sumLinearF1' => [self::class, 'sumLinearF1'],
    //         'sumLinearF2' => [self::class, 'sumLinearF2'],
    //         // 'sumInCycle' => [self::class, 'sumInCycle'],
    //         // // 'sumInArray' => [self::class, 'sumInArray'],
    //         // 'sumRange' => [self::class, 'sumRange'],
    //     ],
    //     arguments: [1, 20_000],
    //     revolutions: 5_000,
    //     iterations: 5,
    // )]
    public static function sumLinearF2(int $a, int $b): int
    {
        $d = $b - $a + 1;
        return ((($d - 1) * $d) >> 1) + $a * $d;
    }

    #[BenchWith(
        [
            'sumInCycle1' => [self::class, 'sumInCycle'],
            'sumInCycle2' => [self::class, 'sumInCycle'],
            'sumInArray' => [self::class, 'sumRange'],
            'sumLinearF' => [self::class, 'sumLinearF1'],
        ],
        arguments: [1, 5_000],
        revolutions: 2000,
        iterations: 10,
    )]
    public static function sumInCycle(int $a, int $b): int
    {
        $result = 0;
        for ($i = $a; $i <= $b; ++$i) {
            $result += $i;
        }

        return $result;
    }

    // #[TestInline([1, 2000], 2001000)]
    public static function sumInCycle2(int $a, int $b): int
    {
        $result = 0;
        for ($i = $a; $i <= $b; ++$i) {
            $result += $i;
        }

        return $result;
    }

    // #[TestInline([24, 2000], 2000724)]
    public static function sumInArray(int $a, int $b): int
    {
        $items = \array_fill($a, $b - $a + 1, null);
        return \array_sum(\array_keys($items));
    }

    // #[TestInline([1, 2000], 2001000)]
    // #[TestInline([24, 2000], 2000724)]
    public static function sumRange(int $a, int $b): int
    {
        return \array_sum(\range($a, $b));
    }
}
