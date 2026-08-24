<?php

declare(strict_types=1);

use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\Plugin\SuitePlugins;
use Testo\Application\Config\SuiteConfig;
use Testo\Bridge\Double\DoublePlugin;

return [
    new SuiteConfig(
        name: 'Bridge/Double/Acceptance',
        location: new FinderConfig(
            include: [__DIR__ . '/Acceptance'],
        ),
        plugins: SuitePlugins::with(new DoublePlugin()),
    ),
    new SuiteConfig(
        name: 'Bridge/Double/Self',
        location: new FinderConfig(
            include: [__DIR__ . '/Self'],
        ),
        plugins: SuitePlugins::with(new DoublePlugin()),
    ),
    // The Feature suite drives Double only through the TestRunner harness (which loads
    // DoublePlugin itself via #[TestingSuite]), so it runs with the default plugin set.
    new SuiteConfig(
        name: 'Bridge/Double/Feature',
        location: new FinderConfig(
            include: [__DIR__ . '/Feature'],
        ),
    ),
];
