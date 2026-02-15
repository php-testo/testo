<?php

declare(strict_types=1);

namespace Testo\Bench\Dto;

final readonly class Value {
    public function __construct(
        public float $min,
        public float $avg,
        public float $max,
        public float $total,
    ) {}
}
