<?php

declare(strict_types=1);

use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\Plugin\SuitePlugins;
use Testo\Application\Config\SuiteConfig;
use Testo\Bench\BenchmarkPlugin;
use Testo\Inline\InlineTestPlugin;

return new ApplicationConfig(
    src: new FinderConfig(
        ['core', 'plugin', 'bridge'],
        [
            'plugin/assert/tests',
            'plugin/bench/tests',
            'plugin/codecov/tests',
            'plugin/convention/tests',
            'plugin/data/tests',
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
                plugins: SuitePlugins::only(
                    new InlineTestPlugin(),
                    new BenchmarkPlugin(),
                ),
            ),
        ],
        # If running in CI, skip the sandbox
        // \filter_var(\getenv('TESTO_CI'), FILTER_VALIDATE_BOOLEAN) ? [] : [
        //     new SuiteConfig(
        //         name: 'sandbox',
        //         location: new FinderConfig(
        //             include: ['tests/Testo'],
        //         ),
        //     ),
        // ],
        require 'plugin/assert/tests/suites.php',
        require 'tests/Common/suites.php',
        require 'plugin/lifecycle/tests/suites.php',
        require 'plugin/data/tests/suites.php',
        require 'plugin/bench/tests/suites.php',
        require 'plugin/inline/tests/suites.php',
        require 'tests/Application/suites.php',
        require 'tests/Output/suites.php',
        require 'plugin/test/tests/suites.php',
        require 'plugin/codecov/tests/suites.php',
        require 'plugin/convention/tests/suites.php',
        require 'plugin/repeat/tests/suites.php',
        require 'plugin/retry/tests/suites.php',
        require 'tests/Tokenizer/suites.php',
    ),
    plugins: [
        new \Testo\Codecov\CodecovPlugin(
            level: \Testo\Codecov\Config\CoverageLevel::Line,
            reports: [
                new \Testo\Codecov\Report\CloverReport(__DIR__ . '/runtime/clover.xml', 'Testo'),
                new \Testo\Codecov\Report\CoberturaReport(__DIR__ . '/runtime/cobertura.xml'),
                new \Testo\Codecov\Report\PhpUnitXmlReport(
                    // Infection's TmpDirProvider appends `/infection` to `tmpDir`, so
                    // when infection.json sets `tmpDir: "runtime"`, Infection looks for
                    // coverage at `runtime/infection/coverage-xml/index.xml`.
                    outputDir: __DIR__ . '/runtime/infection/coverage-xml',
                ),
            ],
        ),
    ],
);
