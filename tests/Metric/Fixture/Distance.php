<?php

declare(strict_types=1);

namespace Tests\Metric\Fixture;

use Testo\Metric\Dimension;
use Testo\Metric\Factor;
use Testo\Metric\UnitConversion;
use Testo\Metric\Unit;

/**
 * A family declared out of order and with a fractional factor, so the ordering and float arithmetic of
 * the metric core are exercised rather than assumed from the built-in families.
 */
#[Dimension('distance')]
enum Distance: string implements Unit
{
    use UnitConversion;

    #[Factor(1_000)]
    case Kilometers = 'km';

    #[Factor(0.01)]
    case Centimeters = 'cm';
    case Meters = 'm';
}
