<?php

declare(strict_types=1);

namespace Tests\Sandbox\Bench;

use Decimal\Decimal;
use Testo\Bench;
use Testo\Filter\Group;

/**
 * Arbitrary-precision decimal arithmetic: BCMath (the always-available baseline, alias `current`)
 * against the `ext-decimal` extension, with a native `float` variant as a speed reference.
 *
 * Two scenarios expose the two cost structures:
 *
 * - {@see withBcMath()} evaluates one invoice-line expression `((price * qty) + fee) / divisor` per
 *   call, so every operand is (re)parsed on every operation. This favours BCMath, which is pure
 *   string math with no object to build, and penalises ext-decimal, which allocates a {@see Decimal}
 *   for each operand every call.
 * - {@see accumulateBcMath()} builds the accumulator and the increment once, then adds the increment
 *   many times in a loop. ext-decimal amortises construction — the loop is pure {@see Decimal::add()}
 *   with no parsing — whereas BCMath still re-parses both operand strings on every `bcadd`.
 *
 * BCMath operates on strings at a fixed scale; ext-decimal carries 34 significant digits; the float
 * variant is deliberately lower precision and exists only to show the cost of correctness, not as an
 * equal-precision rival. Because the three sit in different precision domains, the winner is measured,
 * not gated: {@see Bench::$tolerance} is `INF`, so the benchmark reports timings and never fails on
 * ranking. Requires the `decimal` extension to be loaded (e.g. `php -d extension=decimal.so`).
 */
#[Group('bench')]
final class DecimalArithmeticBench
{
    #[Bench(
        callables: [
            'ext-decimal' => [self::class, 'withDecimal'],
            'native-float' => [self::class, 'withFloat'],
        ],
        arguments: ['19.99', '3', '4.50', '7'],
        warmup: 30_000,
        calls: 10_000,
        iterations: 20,
        tolerance: \INF,
    )]
    public static function withBcMath(string $price, string $qty, string $fee, string $divisor): string
    {
        return \bcdiv(\bcadd(\bcmul($price, $qty, 10), $fee, 10), $divisor, 10);
    }

    public static function withDecimal(string $price, string $qty, string $fee, string $divisor): string
    {
        return Decimal::valueOf($price)
            ->mul(Decimal::valueOf($qty))
            ->add(Decimal::valueOf($fee))
            ->div(Decimal::valueOf($divisor))
            ->toString();
    }

    public static function withFloat(string $price, string $qty, string $fee, string $divisor): string
    {
        return (string) (((float) $price * (float) $qty + (float) $fee) / (float) $divisor);
    }

    #[Bench(
        callables: [
            'ext-decimal' => [self::class, 'accumulateDecimal'],
            'native-float' => [self::class, 'accumulateFloat'],
        ],
        arguments: ['0', '0.01', 2_000],
        warmup: 300,
        calls: 100,
        iterations: 20,
        tolerance: \INF,
    )]
    public static function accumulateBcMath(string $start, string $increment, int $times): string
    {
        $acc = $start;
        for ($i = 0; $i < $times; $i++) {
            $acc = \bcadd($acc, $increment, 2);
        }

        return $acc;
    }

    public static function accumulateDecimal(string $start, string $increment, int $times): string
    {
        $acc = Decimal::valueOf($start);
        $inc = Decimal::valueOf($increment);
        for ($i = 0; $i < $times; $i++) {
            $acc = $acc->add($inc);
        }

        return $acc->toString();
    }

    public static function accumulateFloat(string $start, string $increment, int $times): string
    {
        $acc = (float) $start;
        $inc = (float) $increment;
        for ($i = 0; $i < $times; $i++) {
            $acc += $inc;
        }

        return (string) $acc;
    }
}
