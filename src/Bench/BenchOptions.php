<?php

declare(strict_types=1);

namespace Testo\Bench;

/**
 * Benchmarks options.
 *
 * @api
 */
final class BenchOptions
{
    public function __construct(
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
        $iterations > 0 or throw new \InvalidArgumentException('Iterations must be greater than 0.');
        $revolutions > 0 or throw new \InvalidArgumentException('Revolutions must be greater than 0.');
    }
}
