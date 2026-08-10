<?php

declare(strict_types=1);

use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\Plugin\SuitePlugins;
use Testo\Application\Config\SuiteConfig;
use Testo\Bench\BenchmarkPlugin;
use Testo\Inline\InlineTestPlugin;

return [
    new SuiteConfig(
        name: 'Bench/Self',
        location: new FinderConfig(
            include: [__DIR__ . '/Self'],
        ),
    ),
    new SuiteConfig(
        name: 'Bench/Unit',
        location: new FinderConfig(
            include: [__DIR__ . '/Unit'],
        ),
    ),
    new SuiteConfig(
        name: 'Bench/Inline',
        location: new FinderConfig(
            include: [__DIR__ . '/../src'],
        ),
        plugins: SuitePlugins::only(
            new InlineTestPlugin(),
            new BenchmarkPlugin(),
        ),
    ),
];
