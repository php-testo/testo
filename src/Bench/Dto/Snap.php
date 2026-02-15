<?php

declare(strict_types=1);

namespace Testo\Bench\Dto;

/**
 * Result of an iteration for a single benchmark.
 */
final readonly class Snap {
    public function __construct(
        public int $revolutions,
        public Value $memory,
        public Value $time,
    ) {}
}
