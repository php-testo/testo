<?php

declare(strict_types=1);

use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Spec\SpecPlugin;

/**
 * Test suites for Spec component.
 */
return [
    new SuiteConfig(
        name: 'Spec/Unit',
        location: new FinderConfig(
            include: [__DIR__ . '/Unit'],
        ),
    ),
    new SuiteConfig(
        name: 'Spec/Feature',
        location: new FinderConfig(
            include: [__DIR__ . '/Feature'],
        ),
        plugins: [
            new SpecPlugin(
                outputDir: __DIR__ . '/runtime',
                collect: true,
            ),
        ],
    ),
];
