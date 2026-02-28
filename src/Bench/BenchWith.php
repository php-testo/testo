<?php

declare(strict_types=1);

namespace Testo\Bench;

use Testo\Bench\Internal\BenchWithInterceptor;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Attribute to specify additional functions to benchmark with.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE)]
#[FallbackInterceptor(BenchWithInterceptor::class)]
final class BenchWith implements Interceptable
{
    public function __construct(
        /**
         * @var array<callable|array{class-string, non-empty-string}> $callables Functions to benchmark with.
         *      It might be a callable or an array with class name and non-public method name.
         */
        public readonly array $callables,

        /**
         * @var array Arguments to pass to the benchmarked functions.
         */
        public readonly array $arguments = [],

        /**
         * @var int<0, max> Number of warmup calls before the actual benchmark iterations.
         */
        public readonly int $warmup = 1,

        /**
         * @var int<1, max> Number of calls per iteration.
         *      Helps reduce the impact of measurement overhead on the benchmark results.
         */
        public readonly int $calls = 1_000,

        /**
         * @var int<1, max> Number of iterations.
         */
        public readonly int $iterations = 5,
    ) {
        $warmup >= 0 or throw new \InvalidArgumentException('Warmup must be greater than or equal to 0.');
        \count($callables) >= 1 or throw new \InvalidArgumentException('At least one callable must be provided.');
        $calls > 0 or throw new \InvalidArgumentException('Calls must be greater than 0.');
        $iterations > 0 or throw new \InvalidArgumentException('Iterations must be greater than 0.');
    }
}
