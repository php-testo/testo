<?php

declare(strict_types=1);

use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;

/**
 * Test suites for the Metric component.
 */
return [
    new SuiteConfig(
        name: 'Metric',
        location: new FinderConfig(
            include: [__DIR__],
            exclude: [__DIR__ . '/Fixture'],
        ),
    ),
];
