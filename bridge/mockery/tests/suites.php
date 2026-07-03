<?php

declare(strict_types=1);

use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\Plugin\SuitePlugins;
use Testo\Application\Config\SuiteConfig;
use Testo\Bridge\Mockery\MockeryPlugin;

return [
    new SuiteConfig(
        name: 'Bridge/Mockery/Acceptance',
        location: new FinderConfig(
            include: [__DIR__ . '/Acceptance'],
        ),
        plugins: SuitePlugins::with(new MockeryPlugin()),
    ),
    new SuiteConfig(
        name: 'Bridge/Mockery/Self',
        location: new FinderConfig(
            include: [__DIR__ . '/Self'],
        ),
        plugins: SuitePlugins::with(new MockeryPlugin()),
    ),
    // The Feature suite drives Mockery only through the TestRunner harness (which loads
    // MockeryPlugin itself via #[TestingSuite]), so it runs with the default plugin set.
    new SuiteConfig(
        name: 'Bridge/Mockery/Feature',
        location: new FinderConfig(
            include: [__DIR__ . '/Feature'],
        ),
    ),
];
