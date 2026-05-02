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
        public ValueRel $avg,

        /** Median of the time values across all iterations. */
        public ValueRel $med,

        /**
         * @var float Standard deviation of the time values across all iterations,
         *      expressed as a percentage of the average.
         */
        public float $rstdev,

        /** Average of the time values across all the filtered iterations. */
        public ValueRel $favg,

        /**
         * @var float Standard deviation of the time values across all the filtered iterations,
         *      expressed as a percentage of the average.
         */
        public float $frstdev,

        /** int<0, max> Number of rejected outliers across all iterations. */
        public int $rejected,

        /**
         * @var list<Report> List of reports for the benchmark, which may include comments, warnings,
         *      or errors related to the benchmark execution.
         */
        public array $reports,
    ) {}
}
