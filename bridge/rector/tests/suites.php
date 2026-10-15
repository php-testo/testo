<?php

declare(strict_types=1);

use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Bridge\Rector\Testing\RectorTestingPlugin;

/**
 * Test suites for the Rector bridge.
 *
 * - `Bridge/Rector` scans the rule sources: {@see RectorTestingPlugin} discovers rules carrying
 *   `#[TestRectorFixtures]` and runs their co-located `*.php.inc` fixtures as data sets.
 * - `Bridge/Rector/Unit` holds the plain unit tests for the harness itself (path resolution,
 *   the containment guard, the attribute).
 * - `Bridge/Rector/Feature` executes converted tests through the Testo pipeline.
 */
return [
    new SuiteConfig(
        name: 'Bridge/Rector',
        location: new FinderConfig(
            include: [\dirname(__DIR__) . '/src'],
        ),
        plugins: [new RectorTestingPlugin()],
    ),
    new SuiteConfig(
        name: 'Bridge/Rector/Unit',
        location: new FinderConfig(
            include: [__DIR__ . '/Unit'],
        ),
    ),
    new SuiteConfig(
        name: 'Bridge/Rector/Feature',
        location: new FinderConfig(include: [__DIR__ . '/Feature']),
    ),
];
