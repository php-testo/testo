<?php

declare(strict_types=1);

namespace Testo\Core\Metric;

use Testo\Metric\Dimension;
use Testo\Metric\Unit;
use Testo\Metric\UnitConversion;

/**
 * A dimensionless quantity — a count of iterations or calls, a rank.
 *
 * @api
 */
#[Dimension('number')]
enum Scalar: string implements Unit
{
    use UnitConversion;
    case Number = 'number';
}
