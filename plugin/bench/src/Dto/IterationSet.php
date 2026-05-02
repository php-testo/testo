<?php

declare(strict_types=1);

namespace Testo\Bench\Dto;

/**
 * Aggregate of results for a single iteration of the benchmark for all the cases.
 */
final readonly class IterationSet
{
    public function __construct(
        /**
         * @var int<1, max> Iteration number, starting from 1.
         */
        public int $number,

        /**
         * @var list<Snap> Results of the iteration.
         */
        public array $cases,
    ) {}
}
