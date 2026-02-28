<?php

declare(strict_types=1);

namespace Testo\Bench\Dto;

/**
 * Aggregate of results for a single benchmark across all iterations.
 */
final readonly class Line
{
    public function __construct(
        /**
         * @var int<1, max> Position of the benchmark in the ranking, starting from 1.
         */
        public int $place,

        /** Name of the case. */
        public string $name,

        /** Average of the time values across all iterations. */
        public ValueRel $mean,

        /** Median of the time values across all iterations. */
        public ValueRel $med,

        /** Average of the time values across all the filtered iterations. */
        public ValueRel $avg,

        /**
         * @var float Standard deviation of the time values across all the filtered iterations,\
         *      expressed as a percentage of the average.
         */
        public float $rstdev,

        /** int<0, max> Number of rejected outliers across all iterations. */
        public int $rejected,
    ) {}
}
