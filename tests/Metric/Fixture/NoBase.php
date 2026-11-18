<?php

declare(strict_types=1);

namespace Tests\Metric\Fixture;

use Testo\Metric\Factor;
use Testo\Metric\UnitConversion;
use Testo\Metric\Unit;

enum NoBase: string implements Unit
{
    use UnitConversion;

    #[Factor(10)]
    case Ten = 'ten';

    #[Factor(100)]
    case Hundred = 'hundred';
}
