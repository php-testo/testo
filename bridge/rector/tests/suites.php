<?php

declare(strict_types=1);

use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Bridge\Rector\Testing\RectorTestingPlugin;

/**
 * Test suite for the Rector bridge.
 *
 * The suite scans the rule sources: {@see RectorTestingPlugin} discovers rules carrying
 * `#[RectorFixtures]` and runs their co-located `*.php.inc` fixtures as data sets.
 */
return [
    new SuiteConfig(
        name: 'Bridge/Rector',
        location: new FinderConfig(
            include: [\dirname(__DIR__) . '/src'],
        ),
        plugins: [new RectorTestingPlugin()],
    ),
];
