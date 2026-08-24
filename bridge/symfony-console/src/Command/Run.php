<?php

declare(strict_types=1);

namespace Testo\Bridge\Symfony\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Testo\Output\Json\JsonPlugin;
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
 * - Different filter types use AND logic (e.g., --filter + --path + --suite + --group)
 * - Groups split into include (OR) and exclude (OR, marked with a leading "!"); exclusion wins
 * - Final result: AND(OR(filters), OR(paths), OR(suites), OR(includeGroups), NOT OR(excludeGroups))
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
 *  # Run only tests in the given groups (OR logic)
 *  ./bin/testo run --group=db --group=integration
 *
 *  # Exclude a group with the "!" prefix (runs everything except the "slow" group)
 *  ./bin/testo run --group=!slow
 *
 *  # Combine groups with name filters (AND between types)
 *  ./bin/testo run --group=db --filter=UserTest
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
            'json',
            null,
            InputOption::VALUE_NONE,
            'Render the run as a single minimalistic JSON object on stdout '
            . '(run summary + failed tests). Intended for LLM agents and CI scripts.',
        );
        $this->addOption(
            'log-json',
            null,
            InputOption::VALUE_REQUIRED,
            'Write the minimalistic JSON report (run summary + failed tests) to the given path. '
            . 'Unlike --json this keeps the human-readable terminal output; mirrors --log-junit.',
        );
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
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Run only test cases of these types (OR logic), e.g. test, inline, bench. '
            . 'Prefix a type with "!" to exclude it instead, e.g. --type=!bench.',
        );
        $this->addOption(
            'group',
            null,
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'Run only tests in these groups (OR logic). '
            . 'Prefix a name with "!" to exclude it instead, e.g. --group=db --group=!slow.',
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
            'coverage-level',
            null,
            InputOption::VALUE_REQUIRED,
            'Depth of coverage analysis: line, branch or path. Overrides the level configured in '
            . 'testo.php. Branch and path require Xdebug; PCOV always collects lines.',
        );
        $this->addOption(
            'log-junit',
            null,
            InputOption::VALUE_REQUIRED,
            'Write JUnit XML report to the given path (overrides JUnitPlugin config). '
            . 'Flag name mirrors PHPUnit / Pest / ParaTest.',
        );
        $this->addOption(
            'log-html',
            null,
            InputOption::VALUE_REQUIRED,
            'Write a self-contained HTML report. A path ending in ".html" produces that single file; '
            . 'anything else is a directory to fill with index.html and its assets. '
            . 'The report opens over file:// with no server.',
        );
        // $this->addOption(
        //     'log-report',
        //     null,
        //     InputOption::VALUE_REQUIRED,
        //     'Write the full run as a versioned JSON document to the given path. '
        //     . 'The data behind the HTML report, on its own, for CI and external tooling.',
        // );
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
        // Exactly one renderer owns stdout — reject conflicting flags instead of silently picking one.
        $teamcity = (bool) $input->getOption('teamcity');
        $json = (bool) $input->getOption('json');
        $teamcity && $json and throw new \InvalidArgumentException(
            'Options --teamcity and --json are mutually exclusive: both render to stdout. Pick one, '
            . 'or use --log-json=<path> to write JSON to a file alongside another renderer.',
        );

        $renderer = match (true) {
            $teamcity => TeamcityPlugin::class,
            $json => JsonPlugin::class,
            default => TerminalPlugin::class,
        };
        $this->container->get($renderer)->configure($this->container);

        // --log-json writes the JSON report to a file alongside the stdout renderer above.
        $logJson = $input->getOption('log-json');
        \is_string($logJson) && $logJson !== ''
            and (new JsonPlugin($logJson))->configure($this->container);

        $result = $this->application->run();

        return $result->status->isSuccessful()
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}
