<?php

declare(strict_types=1);

use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;

/**
 * Test suites for Repeat component.
 */
return [
    new SuiteConfig(
        name: 'Repeat: Unit',
        location: new FinderConfig(
            include: [__DIR__ . '/Unit'],
        ),
    ),
    new SuiteConfig(
        name: 'Repeat: Feature',
        location: new FinderConfig(
            include: [__DIR__ . '/Feature'],
        ),
    ),
];
