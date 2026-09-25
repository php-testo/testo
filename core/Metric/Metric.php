<?php

declare(strict_types=1);

namespace Testo\Metric;

use Testo\Metric\Exception\IncompatibleUnits;

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

    /**
     * The same amount in another unit of the same family.
     *
     * @template TTo of Unit
     * @param TTo $unit
     * @return self<TTo>
     * @throws IncompatibleUnits When `$unit` belongs to a different family.
     */
    public function to(Unit $unit): self
    {
        return new self(Units::convert($this->value, $this->unit, $unit), $unit);
    }

    /**
     * The same amount in the unit of its family that reads most compactly; see {@see UnitFamily::fit()}.
     *
     * @return self<TUnit>
     */
    public function compact(): self
    {
        return Units::compact($this->value, $this->unit);
    }
}
