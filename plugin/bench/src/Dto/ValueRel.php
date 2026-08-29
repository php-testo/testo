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
         * @var float Difference from the baseline — the `current` case — in percentage. Zero for the
         *      baseline itself; positive means slower than it, negative means faster.
         *
         * The formula for calculating this is: ((current_value - baseline_value) / baseline_value) * 100
         */
        public float $diff,
    ) {}
}
