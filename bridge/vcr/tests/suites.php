<?php

declare(strict_types=1);

use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\Plugin\SuitePlugins;
use Testo\Application\Config\SuiteConfig;
use Testo\Bridge\Vcr\VcrPlugin;

return [
    new SuiteConfig(
        name: 'Bridge/Vcr/Acceptance',
        location: new FinderConfig(
            include: [__DIR__ . '/Acceptance'],
        ),
        plugins: SuitePlugins::with(new VcrPlugin()),
    ),
    new SuiteConfig(
        name: 'Bridge/Vcr/Self',
        location: new FinderConfig(
            include: [__DIR__ . '/Self'],
        ),
        plugins: SuitePlugins::with(new VcrPlugin()),
    ),
];
