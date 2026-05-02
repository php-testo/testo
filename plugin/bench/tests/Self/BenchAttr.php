<?php

declare(strict_types=1);

namespace Tests\Bench\Self;

use Testo\Bench;
use Testo\Inline\TestInline;

final class BenchAttr
{
    #[Bench(
        [
            'shift' => [self::class, 'sumLinearF2'],
            'multi' => [self::class, 'sumLinearF3'],
            'recursion' => [self::class, 'rangeSum'],
        ],
        arguments: [1, 20_000],
        calls: 20,
        iterations: 10,
    )]
    #[TestInline([1, 2000], 2001000)]
    #[TestInline([24, 2000], 2000724)]
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

    #[TestInline([1, 2000], 2001000)]
    #[TestInline([24, 2000], 2000724)]
    public static function sumLinearF2(int $a, int $b): int
    {
        $d = $b - $a + 1;
        return ((($d - 1) * $d) >> 1) + $a * $d;
    }

    #[TestInline([1, 2000], 2001000)]
    #[TestInline([24, 2000], 2000724)]
    public static function rangeSum(int $a, int $b): int
    {
        return $a === $b ? $a : $a + self::rangeSum($a + 1, $b);
    }

    // #[Bench(
    //     [
    //         'sumInArray' => [self::class, 'sumRange'],
    //         'sumLinearF' => [self::class, 'sumLinearF1'],
    //     ],
    //     arguments: [1, 5_000],
    //     calls: 20,
    //     iterations: 10,
    // )]
    #[Bench(
        [
            'sumInArray' => [self::class, 'sumRange'],
            'sumLinearF' => [self::class, 'sumLinearF1'],
            'eval1' => [self::class, 'eval1'],
            'recursion' => [self::class, 'rangeSum'],
        ],
        arguments: [1, 5_000],
        warmup: 10,
        calls: 20,
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

    #[TestInline([24, 2000], 2000724)]
    public static function sumRange(int $a, int $b): int
    {
        return \array_sum(\range($a, $b));
    }

    #[TestInline([24, 2000], 2000724)]
    public static function eval1(int $a, int $b): int
    {
        return eval('return ' . \implode('+', \range($a, $b)) . ';');
    }

    #[TestInline([24, 2000], 2000724)]
    public static function eval2(int $a, int $b): int
    {
        eval('$a = ' . \implode('+', \range($a, $b)) . ';');
        return $a;
    }
}
