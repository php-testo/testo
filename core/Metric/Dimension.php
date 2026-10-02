<?php

declare(strict_types=1);

namespace Testo\Metric;

/**
 * Names the physical quantity a {@see Unit} enum measures.
 *
 * Placed on the enum itself. The name is what a reporter shows or keys on when it does not care about
 * the particular unit — `time`, `memory` — and what an error names when two units are mixed. An enum
 * without it is still a valid unit family: the name falls back to the enum's short class name in lower case.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Dimension
{
    /**
     * @param non-empty-string $name
     */
    public function __construct(
        public string $name,
    ) {}
}
