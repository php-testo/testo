<?php

declare(strict_types=1);

namespace Testo\Core\Metric;

use Testo\Metric\Dimension;
use Testo\Metric\Factor;
use Testo\Metric\Unit;
use Testo\Metric\UnitConversion;

/**
 * An amount of memory, in bytes or one of their binary (IEC) multiples — the scale PHP's own memory
 * figures and the tools around it use, named so a kibibyte is never mistaken for a thousand bytes.
 *
 * @api
 */
#[Dimension('memory')]
enum Memory: string implements Unit
{
    use UnitConversion;

    #[Factor(1)]
    case Bytes = 'B';

    #[Factor(1024)]
    case Kibibytes = 'KiB';

    #[Factor(1024 ** 2)]
    case Mebibytes = 'MiB';

    #[Factor(1024 ** 3)]
    case Gibibytes = 'GiB';
}
