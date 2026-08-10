<?php

declare(strict_types=1);

use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;

/**
 * Test suites for the Revolt bridge.
 */
return [
    new SuiteConfig(
        name: 'Revolt/Self',
        location: new FinderConfig(
            include: [__DIR__ . '/Self'],
        ),
    ),
    new SuiteConfig(
        name: 'Revolt/Unit',
        location: new FinderConfig(
            include: [__DIR__ . '/Unit'],
        ),
    ),
];
