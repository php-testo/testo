<?php

declare(strict_types=1);

namespace Testo\Metric;

/**
 * The {@see Unit} arithmetic for an enum that declares itself through {@see Dimension} and {@see Factor}.
 *
 * Everything is delegated to {@see Units}, so the enum contributes only its cases and attributes.
 *
 * @api
 */
trait UnitConversion
{
    public function factor(): int|float
    {
        return Units::factor($this);
    }

    public function convert(int|float $value, Unit $to): int|float
    {
        return Units::convert($value, $this, $to);
    }
}
