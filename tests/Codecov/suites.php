<?php

declare(strict_types=1);

use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;

return [
    new SuiteConfig(
        name: 'Codecov/Unit',
        location: new FinderConfig(
            include: [__DIR__ . '/Unit'],
        ),
    ),
];
