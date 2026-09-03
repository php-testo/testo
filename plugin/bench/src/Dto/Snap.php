<?php

declare(strict_types=1);

namespace Testo\Bench\Dto;

/**
 * Result of an iteration for a single benchmark.
 */
final readonly class Snap
{
    public function __construct(
        /** @var int<1, max> Number of calls in the iteration. */
        public int $calls,

        /** @var int<0, max> Peak memory allocated during the iteration, in bytes */
        public int $memory,

        /** Sum of time across all calls in microseconds. */
        public float $time,
    ) {}
}
