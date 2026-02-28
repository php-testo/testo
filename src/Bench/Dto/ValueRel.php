<?php

declare(strict_types=1);

namespace Testo\Bench\Dto;

final readonly class ValueRel
{
    public function __construct(
        /**
         * @var float Absolute value of the metric (e.g., time in seconds, memory in bytes).
         */
        public float $value,

        /**
         * @var float Difference from the baseline value (the best-performing benchmark) in percentage.
         *      For the baseline, this will be 0.0. For others, can be positive (worse) or negative (better).
         *
         * The formula for calculating this is: ((current_value - baseline_value) / baseline_value) * 100
         */
        public float $diff,
    ) {}
}
