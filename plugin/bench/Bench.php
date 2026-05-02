<?php

declare(strict_types=1);

namespace Testo;

use Testo\Bench\Internal\Pipeline\BenchInterceptor;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Marks a method or function as a benchmark subject and compares its performance against
 * the provided callables.
 *
 * The marked method (or function) automatically receives the alias `current`.
 * All callables listed in {@see Bench::$callables} are benchmarked under the same conditions.
 * Results are ranked by filtered average time — the fastest callable takes first place
 * and serves as the baseline for relative comparison.
 *
 * ## How it works
 *
 * 1. Each callable is invoked `$calls` times per iteration (to reduce measurement noise).
 * 2. This is repeated for `$iterations` iterations (to collect statistical data).
 * 3. Outliers are detected and filtered using the MAD (Median Absolute Deviation) method.
 * 4. Results are ranked by filtered average time — the fastest callable wins.
 *
 * ## Aliases
 *
 * - The marked method always has the alias `current`.
 * - For other callables, use **string keys** in the `$callables` array to assign aliases.
 *   These aliases appear in the results table for easy identification.
 *
 * ## Usage examples
 *
 * Basic — compare the current method against one alternative:
 *
 * ```
 *  #[Bench(['array_sum' => [self::class, 'sumWithArraySum']])]
 *  public static function sumWithLoop(int $a, int $b): int { ... }
 * ```
 *
 * With arguments and tuning parameters:
 *
 * ```
 *  #[Bench(
 *      [
 *          'shift'  => [self::class, 'secondImpl'],
 *          'multi'  => [self::class, 'thirdImpl'],
 *      ],
 *      arguments: [1, 20_000],
 *      calls: 100_000,
 *      iterations: 10,
 *  )]
 *  public static function firstImpl(int $a, int $b): int { ... }
 * ```
 *
 * The attribute is repeatable — you can attach multiple `#[BenchWith]` to a single method
 * to run independent benchmark scenarios with different callables or arguments.
 *
 * ## Stability
 *
 * Results are considered stable when the relative standard deviation (RStDev) is below 2%.
 * If variance is too high, Testo will generate diagnostic reports with actionable advice
 * (e.g., increase `$calls` or `$iterations`).
 *
 * @link https://php-testo.github.io/blog/collider.md
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE)]
#[FallbackInterceptor(BenchInterceptor::class)]
final readonly class Bench implements Interceptable
{
    public function __construct(
        /**
         * Functions to benchmark against the current (marked) method.
         *
         * Each entry can be:
         * - A callable (closure, function name, `[object, method]`, etc.)
         * - An array `[class-string, non-empty-string]` to reference a non-public method by class and name.
         *
         * Use **string keys** to assign aliases that will appear in the results table:
         *
         * ```
         *  ['shift' => [self::class, 'altImpl'], 'naive' => 'str_replace']
         * ```
         *
         * Numeric keys are converted to string and used as-is.
         *
         * @var array<callable|array{class-string, non-empty-string}>
         */
        public array $callables,

        /**
         * Arguments passed to every benchmarked function (including `current`).
         *
         * All functions receive the same arguments to ensure a fair comparison.
         *
         * @var array
         */
        public array $arguments = [],

        /**
         * Number of warmup calls before the actual benchmark iterations.
         *
         * Warmup runs eliminate cold-start overhead (lazy initialization, first-call allocations, etc.).
         * Results from warmup calls are discarded.
         *
         * @var int<0, max>
         */
        public int $warmup = 1,

        /**
         * Number of function calls per iteration.
         *
         * Higher values reduce the impact of measurement overhead and produce more stable results.
         * If RStDev is too high, try increasing this value.
         *
         * @var int<1, max>
         */
        public int $calls = 1_000,

        /**
         * Number of iterations (rounds).
         *
         * Each iteration runs all functions {@see $calls} times and records timing and memory data.
         * More iterations improve statistical reliability and outlier detection.
         *
         * @var int<1, max>
         */
        public int $iterations = 10,
    ) {
        $warmup >= 0 or throw new \InvalidArgumentException('Warmup must be greater than or equal to 0.');
        \count($callables) >= 1 or throw new \InvalidArgumentException('At least one callable must be provided.');
        $calls > 0 or throw new \InvalidArgumentException('Calls must be greater than 0.');
        $iterations > 0 or throw new \InvalidArgumentException('Iterations must be greater than 0.');
    }
}
