<?php

declare(strict_types=1);

use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\Plugin\SuitePlugins;
use Testo\Application\Config\SuiteConfig;
use Testo\Inline\InlineTestPlugin;

return [
    new SuiteConfig(
        name: 'Inline/Inline',
        location: new FinderConfig(
            include: [__DIR__ . '/../src'],
        ),
        plugins: SuitePlugins::only(
            new InlineTestPlugin(),
        ),
    ),
];
