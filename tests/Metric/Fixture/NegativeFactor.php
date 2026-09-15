<?php

declare(strict_types=1);

namespace Tests\Metric\Fixture;

use Testo\Metric\Factor;
use Testo\Metric\UnitConversion;
use Testo\Metric\Unit;

enum NegativeFactor: string implements Unit
{
    use UnitConversion;
    case Base = 'base';

    #[Factor(-2)]
    case Broken = 'broken';
}
