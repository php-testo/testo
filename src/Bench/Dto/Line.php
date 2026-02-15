<?php

declare(strict_types=1);

namespace Testo\Bench\Dto;

/**
 * Aggregate of results for a single benchmark across all iterations.
 */
final readonly class Line {
    public function __construct(
        /**
         * @var int<1, max> Position of the benchmark in the ranking, starting from 1.
         */
        public int $place,
        public string $name,

        /** Max memory usage across all iterations in bytes. */
        public ValueRel $memory,

        /** Average time across all iterations in microseconds. */
        public ValueRel $time,

        /**
         * @var float Standard deviation of the time values across all iterations.
         */
        public float $rstdev,
    ) {}
}
