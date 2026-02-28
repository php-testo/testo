<?php

declare(strict_types=1);

namespace Testo\Bench\Dto;

/**
 * Aggregate of results for a single case across all iterations.
 */
final readonly class CaseSet
{
    public function __construct(
        /** @var string Name of the case, as provided in the benchmark definition. */
        public string $name,

        /** @var list<Snap> Results of iterations */
        public array $iterations,
    ) {}
}
