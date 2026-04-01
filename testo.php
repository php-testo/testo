<?php

declare(strict_types=1);

use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;

return new ApplicationConfig(
    suites: [
        new SuiteConfig(
            name: 'sandbox',
            location: new FinderConfig(
                include: ['tests/Testo'],
            ),
        ),
    ],
    plugins: [
        new \Testo\FlakyPRCommentPlugin(),
    ],
);
