<?php

declare(strict_types=1);

use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;

/**
 * Test suites for the Fiber component.
 */
return [
    new SuiteConfig(
        name: 'Fiber/Unit',
        location: new FinderConfig(
            include: [__DIR__ . '/Unit'],
        ),
    ),
    new SuiteConfig(
        name: 'Fiber/Feature',
        location: new FinderConfig(
            include: [__DIR__ . '/Feature'],
        ),
    ),
    new SuiteConfig(
        name: 'Fiber/Self',
        location: new FinderConfig(
            include: [__DIR__ . '/Self'],
        ),
    ),
];
