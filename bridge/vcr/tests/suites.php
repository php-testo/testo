<?php

declare(strict_types=1);

use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\Plugin\SuitePlugins;
use Testo\Application\Config\SuiteConfig;
use Testo\Bridge\VCR\VcrPlugin;

return [
    new SuiteConfig(
        name: 'Bridge/Vcr/Acceptance',
        location: new FinderConfig(
            include: [__DIR__ . '/Acceptance'],
        ),
        plugins: SuitePlugins::with(new VcrPlugin(cassettePath: __DIR__ . '/fixtures')),
    ),
    new SuiteConfig(
        name: 'Bridge/Vcr/Self',
        location: new FinderConfig(
            include: [__DIR__ . '/Self'],
        ),
        plugins: SuitePlugins::with(new VcrPlugin(cassettePath: __DIR__ . '/fixtures')),
    ),
    // The Feature suite drives VCR only through the TestRunner harness (which loads VcrPlugin itself
    // via #[TestingSuite]), so it runs with the default plugin set.
    new SuiteConfig(
        name: 'Bridge/Vcr/Feature',
        location: new FinderConfig(
            include: [__DIR__ . '/Feature'],
        ),
    ),
];
