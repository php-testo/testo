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

        /** Standard deviation of the time values across all iterations. */
        public float $rstdev,

        /** @var int<0, max> Number of iterations that were rejected from the final results. */
        public int $rejected,

        /** Average time across all the filtered iterations in microseconds. */
        public float $favg,

        /** Standard deviation of the time values across all the filtered iterations. */
        public float $frstdev,
    ) {}
}
