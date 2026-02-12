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
         * @var int<1, max> Number of iterations to run for each benchmark.
         */
        public readonly int $iterations = 1000,

        /**
         * @var int<1, max> Number of revolutions to run for each benchmark.
         *      A revolution is a single execution of the benchmarked function.
         */
        public readonly int $revolutions = 5,
    ) {
        \count($callables) < 1 or throw new \InvalidArgumentException('At least one callable must be provided.');
        $iterations > 0 or throw new \InvalidArgumentException('Iterations must be greater than 0.');
        $revolutions > 0 or throw new \InvalidArgumentException('Revolutions must be greater than 0.');
    }
}
