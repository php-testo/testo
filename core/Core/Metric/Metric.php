<?php

declare(strict_types=1);

namespace Testo\Core\Metric;

/**
 * One number a test produced, together with the unit it was measured in.
 *
 * The unit travels with the value rather than baked into a name, so each reporter renders it its own way
 * — a TeamCity number `type`, a JUnit property-name suffix — instead of parsing it back out of a key. The
 * unit's family is a type parameter, so a `Metric<Percent>` is distinct from a `Metric<Time>` and a
 * consumer keyed to a family cannot be handed the wrong one.
 *
 * @template-covariant TUnit of Unit
 * @api
 */
final readonly class Metric
{
    /**
     * @param TUnit $unit
     */
    public function __construct(
        public int|float $value,
        public Unit $unit,
    ) {}
}
