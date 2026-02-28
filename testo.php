<?php

declare(strict_types=1);

use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;

return new ApplicationConfig(
    suites: \array_merge(
        [
            new SuiteConfig(
                name: 'default',
                location: new FinderConfig(
                    include: ['tests/Testo'],
                ),
            ),
        ],
        require 'tests/Assert/suites.php',
        require 'tests/Common/suites.php',
        require 'tests/Lifecycle/suites.php',
        require 'tests/Data/suites.php',
        require 'tests/Bench/suites.php',
    ),
);
