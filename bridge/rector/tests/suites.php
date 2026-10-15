<?php

declare(strict_types=1);

use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Bridge\Rector\Testing\RectorTestingPlugin;

// Reflection metadata only: runtime PHPUnit classes would affect other bridges in this process.
(new \Rector\Config\RectorConfig())->phpstanConfig(__DIR__ . '/Stub/phpstan.neon');

/**
 * Test suites for the Rector bridge.
 *
 * - `Bridge/Rector` scans the rule sources: {@see RectorTestingPlugin} discovers rules carrying
 *   `#[TestRectorFixtures]` and runs their co-located `*.php.inc` fixtures as data sets.
 * - `Bridge/Rector/Unit` holds the plain unit tests for the harness itself (path resolution,
 *   the containment guard, the attribute).
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
];
