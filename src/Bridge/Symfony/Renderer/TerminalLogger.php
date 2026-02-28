<?php

declare(strict_types=1);

namespace Testo\Bridge\Symfony\Renderer;

use Testo\Assert\TestState;
use Testo\Common\Environment;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\SuiteInfo;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Data\MultipleResult;

/**
 * Terminal logger for test reporting with configurable output format.
 *
 * @internal
 */
final class TerminalLogger
{
    /** @var int<0, max> */
    private int $totalTests = 0;

    /** @var int<0, max> */
    private int $passedTests = 0;

    /** @var int<0, max> */
    private int $failedTests = 0;

    /** @var int<0, max> */
    private int $skippedTests = 0;

    /** @var int<0, max> */
    private int $riskyTests = 0;

    /** @var list<array{result: TestResult, duration: int<0, max>|null}> */
    private array $failures = [];

    /**
     * Current indentation level for nested tests (e.g., DataProvider datasets).
     *
     * @var int<0, max>
     */
    private int $currentIndentLevel = 0;

    /**
     * Override name for the current test (e.g., dataset name).
     */
    private ?string $currentTestName = null;

    public function __construct(
        private readonly OutputFormat $format = OutputFormat::Compact,
    ) {}

    /**
     * Publishes test suite started message.
     */
    public function suiteStartedFromInfo(SuiteInfo $info): void
    {
        echo Formatter::suiteHeader($info->name, $this->format);
    }

    /**
     * Handles test suite result.
     */
    public function handleSuiteResult(SuiteInfo $info, SuiteResult $result): void
    {
        echo Formatter::suiteSummary($result, $this->format);
    }

    /**
     * Publishes test case started message.
     */
    public function caseStartedFromInfo(CaseInfo $info): void
    {
        echo Formatter::caseHeader($info->name, $this->format);
    }

    /**
     * Handles test case result.
     */
    public function handleCaseResult(CaseInfo $info, CaseResult $result): void
    {
        echo Formatter::caseFooter($this->format);
        echo Formatter::caseSummary($result, $this->format);
    }

    /**
     * Publishes test batch started message (for DataProvider tests).
     */
    public function batchStartedFromInfo(TestInfo $info): void
    {
        $this->currentIndentLevel = 1;

        if ($this->format === OutputFormat::Dots) {
            return;
        }

        // Print the batch test name (the main test with DataProvider)
        $indent = $this->format === OutputFormat::Verbose ? '     ' : '   ';
        $symbol = Style::dim(Symbol::DataProvider->value);
        echo "{$indent}{$symbol} {$info->name}\n";
    }

    /**
     * Publishes test batch finished message (for DataProvider tests).
     */
    public function batchFinishedFromInfo(TestInfo $info): void
    {
        $this->currentIndentLevel = 0;
        // No visual output for batch finish in terminal mode
    }

    /**
     * Publishes test started message.
     *
     * @param non-empty-string|null $overrideName Optional override for the test name (e.g., dataset name)
     */
    public function testStartedFromInfo(TestInfo $info, ?string $overrideName = null): void
    {
        $this->currentTestName = $overrideName;
        // No output on test start for compact/dots mode
    }

    /**
     * Handles test result and updates statistics.
     *
     * @param int<0, max>|null $duration Duration in milliseconds
     */
    public function handleTestResult(TestResult $result, ?int $duration): void
    {
        $this->totalTests++;

        match ($result->status) {
            Status::Passed, Status::Flaky => $this->handlePassedTest($result, $duration),
            Status::Failed, Status::Error, Status::Aborted => $this->handleFailedTest($result, $duration),
            Status::Skipped, Status::Cancelled => $this->handleSkippedTest($result, $duration),
            Status::Risky => $this->handleRiskyTest($result, $duration),
        };
    }

    /**
     * Prints final summary with all failures and statistics.
     */
    public function printSummary(float $duration): void
    {
        $this->printFailures();
        $this->printStatistics($duration);
    }

    /**
     * Ensures run header is printed once.
     */
    public function ensureHeader(): void
    {
        echo Formatter::runHeader();
    }

    public function printEnvironment(): void
    {
        echo \sprintf(' %s %s (%s)', Style::info('OS:'), Environment::getOs(), Environment::getCpu()) . "\n";
        echo \sprintf(' %s %s (%s, memory: %s)', Style::info('PHP:'), Environment::getPhpVersion(), \PHP_SAPI, \ini_get('memory_limit') ?: 'unlimited') . "\n";

        $modes = Environment::getXDebugMode();
        $xdebug = match (true) {
            !Environment::hasXDebug() => 'off',
            $modes !== [] => Environment::getXDebugVersion() . Style::dim(' (' . \implode(', ', $modes) . ')'),
            default => Environment::getXDebugVersion() . Style::dim(' (off)'),
        };
        echo \sprintf('   %s %s', Style::info('XDebug:'), $xdebug) . "\n";

        $opcache = match (true) {
            !Environment::isOpCacheEnabled() => 'off',
            Environment::isJitEnabled() => 'enabled with JIT',
            default => 'enabled',
        };
        echo \sprintf('   %s %s', Style::info('OPcache:'), $opcache) . "\n\n";
    }

    /**
     * Handles passed test status.
     *
     * @param int<0, max>|null $duration
     */
    private function handlePassedTest(TestResult $result, ?int $duration): void
    {
        $this->passedTests++;

        $item = new FormattedItem(
            name: $this->currentTestName ?? $result->info->name,
            status: $result->status,
            duration: $duration,
            indentLevel: $this->currentIndentLevel,
            description: (string) $result->getAttribute('description'),
        );

        echo Formatter::formatRun($item, $this->format);
        $this->printMultipleRuns($result);
        $this->currentTestName = null;
    }

    /**
     * Handles failed test status.
     *
     * @param int<0, max>|null $duration
     */
    private function handleFailedTest(TestResult $result, ?int $duration): void
    {
        $this->failedTests++;
        $this->failures[] = ['result' => $result, 'duration' => $duration];

        $item = new FormattedItem(
            name: $this->currentTestName ?? $result->info->name,
            status: $result->status,
            duration: $duration,
            indentLevel: $this->currentIndentLevel,
            description: (string) $result->getAttribute('description'),
        );

        echo Formatter::formatRun($item, $this->format);
        $this->printMultipleRuns($result);
        $this->printAssertionHistory($result);
        $this->currentTestName = null;
    }

    /**
     * Prints multiple test runs if available.
     */
    private function printMultipleRuns(TestResult $result): void
    {
        if ($this->format === OutputFormat::Dots) {
            return;
        }

        $multipleResult = $result->getAttribute(MultipleResult::class);

        if ($multipleResult === null) {
            return;
        }

        $runNumber = 1;
        foreach ($multipleResult->results as $runKey => $runResult) {
            $item = new FormattedItem(
                name: "Run #{$runNumber}",
                status: $runResult->status,
                duration: null,
                indentLevel: 1,
                description: (string) $runKey,
            );

            echo Formatter::formatRun($item, $this->format);
            $runNumber++;
        }
    }

    /**
     * Prints assertion history for a test result if available.
     */
    private function printAssertionHistory(TestResult $result): void
    {
        $testState = $result->getAttribute(TestState::class);

        if ($testState === null || $testState->history === []) {
            return;
        }

        echo Formatter::assertionHistoryHeader($this->format);

        foreach ($testState->history as $assertion) {
            echo Formatter::assertionLine($assertion, $this->format);
        }
    }

    /**
     * Handles skipped test status.
     *
     * @param int<0, max>|null $duration
     */
    private function handleSkippedTest(TestResult $result, ?int $duration): void
    {
        $this->skippedTests++;

        $item = new FormattedItem(
            name: $this->currentTestName ?? $result->info->name,
            status: $result->status,
            duration: $duration,
            indentLevel: $this->currentIndentLevel,
        );

        echo Formatter::formatRun($item, $this->format);
        $this->currentTestName = null;
    }

    /**
     * Handles risky test status.
     *
     * @param int<0, max>|null $duration
     */
    private function handleRiskyTest(TestResult $result, ?int $duration): void
    {
        $this->riskyTests++;

        $item = new FormattedItem(
            name: $this->currentTestName ?? $result->info->name,
            status: $result->status,
            duration: $duration,
            indentLevel: $this->currentIndentLevel,
        );

        echo Formatter::formatRun($item, $this->format);
        $this->currentTestName = null;
    }

    /**
     * Prints all failures with details.
     */
    private function printFailures(): void
    {
        if ($this->failures === []) {
            return;
        }

        echo Formatter::failuresHeader();

        $index = 1;
        foreach ($this->failures as $failure) {
            $result = $failure['result'];
            $duration = $failure['duration'];
            $throwable = $result->failure;

            $message = $throwable?->getMessage() ?? 'Test failed';
            $details = $throwable !== null
                ? Helper::formatException(
                    $throwable,
                    function: $result->info->testDefinition->reflection,
                    maxPreviousDepth: 1,
                )
                : '';

            echo Formatter::failureDetail(
                $index,
                $result->info->name,
                $message,
                $details,
                $duration,
            );

            $index++;
        }
    }

    /**
     * Prints final statistics.
     */
    private function printStatistics(float $duration): void
    {
        $success = $this->failedTests === 0;

        echo Formatter::summary(
            $this->totalTests,
            $this->passedTests,
            $this->failedTests,
            $this->skippedTests,
            $this->riskyTests,
            $duration,
        );

        echo Formatter::finalBanner($success);
    }
}
