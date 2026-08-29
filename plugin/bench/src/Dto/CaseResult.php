<?php

declare(strict_types=1);

namespace Testo\Bench\Dto;

/**
 * Aggregate of results for a single case across all iterations.
 */
final readonly class CaseResult
{
    public function __construct(
        /** Average time across all iterations in microseconds. */
        public float $mean,

        /** Median time across all iterations in microseconds. */
        public float $med,

        /** Relative standard deviation across all iterations, as a percentage of the mean. */
        public float $rstdev,

        /** @var int<0, max> Number of iterations that were rejected from the final results. */
        public int $rejected,

        /** Average time across all the filtered iterations in microseconds. */
        public float $favg,

        /** Relative standard deviation across the filtered iterations, as a percentage of the filtered mean. */
        public float $frstdev,
    ) {}
}
