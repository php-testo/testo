<?php

declare(strict_types=1);

use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\Plugin\SuitePlugins;
use Testo\Application\Config\SuiteConfig;
use Testo\Bridge\Double\DoublePlugin;

# Double requires PHP 8.3+, above Testo's 8.2 floor. On 8.2 the bridge sits out.
if (PHP_VERSION_ID < 80300) {
    return [];
}

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
