<?php

declare(strict_types=1);

use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\Plugin\SuitePlugins;
use Testo\Application\Config\SuiteConfig;
use Testo\Bench\BenchmarkPlugin;
use Testo\Inline\InlineTestPlugin;

return new ApplicationConfig(
    src: ['src'],
    suites: \array_merge(
        [
            new SuiteConfig(
                name: 'SRC',
                location: new FinderConfig(
                    include: ['src'],
                ),
                plugins: SuitePlugins::only(
                    new InlineTestPlugin(),
                    new BenchmarkPlugin(),
                ),
            ),
        ],
        # If running in CI, skip the sandbox
        \filter_var(\getenv('TESTO_CI'), FILTER_VALIDATE_BOOLEAN) ? [] : [
            new SuiteConfig(
                name: 'sandbox',
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
        require 'tests/Application/suites.php',
        require 'tests/Output/suites.php',
        require 'tests/Test/suites.php',
        require 'tests/Codecov/suites.php',
        require 'tests/Repeat/suites.php',
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
