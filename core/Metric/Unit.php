<?php

declare(strict_types=1);

namespace Testo\Metric;

use Testo\Metric\Exception\IncompatibleUnits;

/**
 * The unit a {@see Metric} was measured in.
 *
 * The parent of every dimension enum ({@see \Testo\Core\Metric\Time}, {@see \Testo\Core\Metric\Memory}, {@see \Testo\Core\Metric\Percent}, {@see \Testo\Core\Metric\Scalar}), so a
 * metric can name a unit from any family while a reporter still handles all of them through one type.
 * Each family is one backed enum whose cases scale within it: the scale is declared with {@see Factor}
 * on the cases and read by {@see Units}, and the {@see UnitConversion} trait implements this interface
 * for such an enum. Converting between families is a reporter's job at its own boundary.
 *
 * @api
 */
interface Unit extends \BackedEnum
{
    /**
     * How many base units of the family one of this unit is worth.
     */
    public function factor(): int|float;

    /**
     * An amount in this unit expressed in another unit of the same family.
     *
     * @throws IncompatibleUnits When `$to` belongs to a different family.
     */
    public function convert(int|float $value, Unit $to): int|float;
}
