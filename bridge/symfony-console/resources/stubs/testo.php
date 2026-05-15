<?php

declare(strict_types=1);

use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\SuiteConfig;

return new ApplicationConfig(
    suites: [
        new SuiteConfig(
            name: 'Unit',
            location: ['__TESTS_UNIT_PATH__'],
        ),
        new SuiteConfig(
            name: 'Sources',
            location: ['__SRC_PATH__'],
        ),
    ],
);
