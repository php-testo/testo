<?php

declare(strict_types=1);

use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\SuiteConfig;

return new ApplicationConfig(
    // For Codecov.
    src: [__SRC_PATH__],
    suites: [
__SUITES__
        // For inline tests and benchmarks right in the project source code, in the src folder.
        new SuiteConfig(
            name: 'Sources',
            location: [__SRC_PATH__],
        ),
    ],
);
