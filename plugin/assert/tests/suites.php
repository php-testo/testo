<?php

declare(strict_types=1);

use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;

/**
 * Test suites for Assert component.
 */
return [
    new SuiteConfig(
        name: 'Assert: Feature',
        location: new FinderConfig(
            include: [__DIR__ . '/Feature'],
        ),
    ),
    new SuiteConfig(
        name: 'Assert/Self',
        location: new FinderConfig(
            include: [__DIR__ . '/Self'],
        ),
    ),
];
