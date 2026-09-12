<?php

declare(strict_types=1);

namespace Testo\Core\Metric;

use Testo\Metric\Dimension;
use Testo\Metric\Unit;
use Testo\Metric\UnitConversion;

/**
 * A ratio already expressed out of 100.
 *
 * @api
 */
#[Dimension('percent')]
enum Percent: string implements Unit
{
    use UnitConversion;
    case Percent = 'percent';
}
