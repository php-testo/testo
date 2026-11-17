<?php

declare(strict_types=1);

namespace Testo\Core\Metric;

use Testo\Metric\Dimension;
use Testo\Metric\Factor;
use Testo\Metric\Unit;
use Testo\Metric\UnitConversion;

/**
 * A duration, in one of the units it may be measured in.
 *
 * @api
 */
#[Dimension('time')]
enum Time: string implements Unit
{
    use UnitConversion;

    #[Factor(1)]
    case Nanoseconds = 'ns';

    #[Factor(1_000)]
    case Microseconds = 'us';

    #[Factor(1_000_000)]
    case Milliseconds = 'ms';

    #[Factor(1_000_000_000)]
    case Seconds = 's';
}
