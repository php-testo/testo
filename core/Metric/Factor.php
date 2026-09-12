<?php

declare(strict_types=1);

namespace Testo\Metric;

/**
 * How many base units one unit of this case is worth.
 *
 * Placed on a {@see Unit} enum case. The base is the case with factor `1` — a case without the attribute
 * has that factor, so a single-case family such as {@see \Testo\Core\Metric\Percent} declares nothing. Converting between
 * two cases is a ratio of their factors, and the compact form of a value is the case whose factor is the
 * largest one the value still exceeds.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS_CONSTANT)]
final readonly class Factor
{
    /**
     * @param int|float $value A positive multiplier to the family's base unit.
     */
    public function __construct(
        public int|float $value,
    ) {}
}
