<?php

declare(strict_types=1);

namespace Tests\Metric\Fixture;

use Testo\Metric\UnitConversion;
use Testo\Metric\Unit;

/**
 * A family that declares nothing: no dimension name and no factors.
 */
enum Plain: string implements Unit
{
    use UnitConversion;
    case One = 'one';
    case Other = 'other';
}
