<?php

declare(strict_types=1);

namespace Testo\Bridge\Symfony\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Testo\Output\Teamcity\TeamcityPlugin;
use Testo\Output\Terminal\TerminalPlugin;

/**
 * Executes test suites with optional filtering and custom output formatting.
 *
 * Runs tests from specified paths with support for method/function filtering,
 * test suite filtering, glob pattern matching for test discovery, and output
 * format selection for different environments (terminal or CI systems like TeamCity).
 *
 * Filter Logic:
 * - Multiple values of same filter type use OR logic (e.g., --filter=test1 --filter=test2)
 * - Different filter types use AND logic (e.g., --filter + --path + --suite)
 * - Final result: AND(OR(filters), OR(paths), OR(suites))
 *
 * ```bash
 *  # Run all tests in default location
 *  ./bin/testo run
 *
 *  # Run tests from specific directory
 *  ./bin/testo run tests/Unit
 *
 *  # Run tests matching glob patterns (wildcards supported)
 *  ./bin/testo run --path="tests/Unit/*Test.php" --path="tests/Integration/*Test.php"
 *
 *  # Filter specific test methods or functions by name (OR logic)
 *  ./bin/testo run --filter=testUserAuthentication --filter=testDatabaseConnection
 *
 *  # Filter specific methods in classes (using short name or FQN)
 *  ./bin/testo run --filter="UserTest::testAuthentication"
 *  ./bin/testo run --filter="Tests\Unit\UserTest::testAuthentication"
 *
 *  # Filter by test suite name (OR logic)
 *  ./bin/testo run --suite=Unit --suite=Integration
 *
 *  # Combine filters with AND logic between types
 *  # Runs tests that match (UserTest::testCreate OR UserTest::testUpdate) AND (Critical suite)
 *  ./bin/testo run --filter=UserTest::testCreate --filter=UserTest::testUpdate --suite=Critical
 *
 *  # Complex filtering: path AND filter AND suite
 *  # Runs tests in Unit directory that match testImportant* AND are in Critical suite
 *  ./bin/testo run --path="tests/Unit/*" --filter=testImportant --suite=Critical
 *
 *  # Run tests with custom config
 *  ./bin/testo run --config=./testo.php
 * ```
 *
 * @internal
 */
#[AsCommand(
    name: 'run',
)]
final class Run extends Base
{
    #[\Override]
    public function configure(): void
    {
        parent::configure();
        $this->addOption('teamcity', null, InputOption::VALUE_NONE);
        $this->addOption(
            'filter',
            null,
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Filter methods or functions to be run',
        );
        $this->addOption(
            'path',
            null,
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Glob patterns for test files to be run',
        );
        $this->addOption(
            'suite',
            null,
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Filter test suites by name',
        );
        $this->addOption(
            'type',
            null,
            InputOption::VALUE_OPTIONAL,
            'Filter test cases by type (e.g. test, test-inline, bench)',
        );
        $this->addOption(
            'coverage',
            null,
            InputOption::VALUE_NONE,
            'Require code coverage collection (fails if no driver available)',
        );
        $this->addOption(
            'no-coverage',
            null,
            InputOption::VALUE_NONE,
            'Disable code coverage collection',
        );
        $this->addOption(
            'log-junit',
            null,
            InputOption::VALUE_REQUIRED,
            'Write JUnit XML report to the given path (overrides JUnitPlugin config). '
            . 'Flag name mirrors PHPUnit / Pest / ParaTest.',
        );
        $this->addOption(
            'coverage-clover',
            null,
            InputOption::VALUE_REQUIRED,
            'Write a Clover XML coverage report to the given file. '
            . 'Implies coverage collection if a driver is available.',
        );
        $this->addOption(
            'coverage-cobertura',
            null,
            InputOption::VALUE_REQUIRED,
            'Write a Cobertura XML coverage report to the given file. '
            . 'Implies coverage collection if a driver is available.',
        );
        $this->addOption(
            'coverage-xml',
            null,
            InputOption::VALUE_REQUIRED,
            'Write a PHPUnit-style coverage XML report to the given directory (consumed by Infection). '
            . 'Implies coverage collection if a driver is available.',
        );
    }

    public function __invoke(
        InputInterface  $input,
        OutputInterface $output,
    ): int {
        $input->getOption('teamcity')
            ? $this->container->get(TeamcityPlugin::class)->configure($this->container)
            : $this->container->get(TerminalPlugin::class)->configure($this->container);

        $result = $this->application->run();

        return $result->status->isSuccessful()
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}
