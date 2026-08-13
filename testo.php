<?php

declare(strict_types=1);

use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Testing\InjectPlugin;

return new ApplicationConfig(
    src: new FinderConfig(
        ['core', 'plugin', 'bridge'],
        [
            'internal/container/tests',
            'bridge/mockery/tests',
            'bridge/rector/tests',
            'bridge/revolt/tests',
            'bridge/symfony-console/tests',
            'bridge/vcr/tests',
            'plugin/assert/tests',
            'plugin/fiber/tests',
            'plugin/bench/tests',
            'plugin/codecov/tests',
            'plugin/convention/tests',
            'plugin/data/tests',
            'plugin/facade/tests',
            'plugin/filter/tests',
            'plugin/inline/tests',
            'plugin/lifecycle/tests',
            'plugin/repeat/tests',
            'plugin/retry/tests',
            'plugin/test/tests',
        ],
    ),
    suites: \array_merge(
        [
            new SuiteConfig(
                name: 'Core/Inline',
                location: new FinderConfig(
                    include: ['core'],
                ),
            ),
        ],
        # If running in CI, skip the sandbox
        \filter_var(\getenv('TESTO_CI'), FILTER_VALIDATE_BOOLEAN) ? [] : [
            new SuiteConfig(
                name: 'sandbox',
                location: new FinderConfig(
                    include: ['tests/Sandbox'],
                ),
            ),
        ],
        require 'internal/container/tests/suites.php',
        require 'internal/fiber/tests/suites.php',
        require 'bridge/mockery/tests/suites.php',
        require 'bridge/rector/tests/suites.php',
        require 'bridge/revolt/tests/suites.php',
        require 'bridge/symfony-console/tests/suites.php',
        require 'bridge/vcr/tests/suites.php',
        require 'plugin/assert/tests/suites.php',
        require 'plugin/fiber/tests/suites.php',
        require 'plugin/bench/tests/suites.php',
        require 'plugin/codecov/tests/suites.php',
        require 'plugin/convention/tests/suites.php',
        require 'plugin/data/tests/suites.php',
        require 'plugin/facade/tests/suites.php',
        require 'plugin/filter/tests/suites.php',
        require 'plugin/inline/tests/suites.php',
        require 'plugin/lifecycle/tests/suites.php',
        require 'plugin/repeat/tests/suites.php',
        require 'plugin/retry/tests/suites.php',
        require 'plugin/test/tests/suites.php',
        require 'tests/Testo/suites.php',
        require 'tests/Application/suites.php',
        require 'tests/Core/suites.php',
        require 'tests/Common/suites.php',
        require 'tests/Output/suites.php',
        require 'tests/Tokenizer/suites.php',
    ),
    plugins: [new InjectPlugin()],
);
