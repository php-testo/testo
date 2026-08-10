<?php

declare(strict_types=1);

use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;

return [
    new SuiteConfig(
        name: 'Core/Context',
        location: new FinderConfig(
            include: [__DIR__ . '/Context'],
        ),
    ),
    new SuiteConfig(
        name: 'Core/Log',
        location: new FinderConfig(
            include: [__DIR__ . '/Log'],
        ),
    ),
    new SuiteConfig(
        name: 'Core/Value',
        location: new FinderConfig(
            include: [__DIR__ . '/Value'],
        ),
    ),
    new SuiteConfig(
        name: 'Core/Pipeline',
        location: new FinderConfig(
            include: [__DIR__ . '/Pipeline'],
        ),
    ),
    new SuiteConfig(
        name: 'Core/Testing/Unit',
        location: new FinderConfig(
            include: [__DIR__ . '/Testing/Unit'],
        ),
    ),
    new SuiteConfig(
        name: 'Core/Testing/Feature',
        location: new FinderConfig(
            include: [__DIR__ . '/Testing/Feature'],
        ),
    ),
];
