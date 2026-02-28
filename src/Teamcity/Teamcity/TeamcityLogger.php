<?php

declare(strict_types=1);

namespace Testo\Teamcity\Teamcity;

use Testo\Assert\State\CompositeRecord;
use Testo\Assert\State\Record;
use Testo\Assert\TestState;
use Testo\Common\Environment;
use Testo\Common\Info;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\SuiteInfo;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;

/**
 * TeamCity logger for test reporting using DTO objects.
 *
 * Publishes TeamCity service messages based on test execution results.
 * Uses TeamcityMessageFormatter for message formatting.
 *
 * @see Formatter for message formatting
 * @internal
 */
final class TeamcityLogger
{
    /**
     * Formats a throwable into a detailed string with class, message, file, line, and stack trace.
     */
    public static function formatThrowable(\Throwable $throwable): string
    {
        $class = $throwable::class;
        $file = $throwable->getFile();
        $line = $throwable->getLine();
        $trace = $throwable->getTraceAsString();

        return "{$class}\nFile: {$file}:{$line}\n\nStack trace:\n{$trace}";
    }

    /**
     * Publishes environment information as a TeamCity block with plain text lines.
     */
    public function logEnvironment(): void
    {
        echo "\033[1m" . Info::NAME . "\033[0m" . self::dim(' v' . Info::version()) . "\n";

        $this->publish(Formatter::blockOpened('Environment'));

        // echo self::key('OS') . \sprintf('%s (%s)', Environment::getOs(), Environment::getCpu()) . "\n";
        echo self::key('PHP') . \sprintf('%s (%s, memory: %s)', Environment::getPhpVersion(), \PHP_SAPI, \ini_get('memory_limit') ?: 'unlimited') . "\n";

        $modes = Environment::getXDebugMode();
        $xdebug = match (true) {
            !Environment::hasXDebug() => self::dim('off'),
            $modes !== [] => Environment::getXDebugVersion() . self::dim(' (' . \implode(', ', $modes) . ')'),
            default => Environment::getXDebugVersion() . self::dim(' (off)'),
        };
        echo '  ' . self::key('XDebug') . $xdebug . "\n";

        $opcache = match (true) {
            !Environment::isOpCacheEnabled() => self::dim('off'),
            Environment::isJitEnabled() => 'enabled with JIT',
            default => 'enabled',
        };
        echo '  ' . self::key('OPcache') . $opcache . "\n";

        $this->publish(Formatter::blockClosed('Environment'));
    }

    /**
     * Publishes test suite started message using SuiteInfo.
     */
    public function suiteStartedFromInfo(SuiteInfo $info): void
    {
        $this->publish(Formatter::suiteStarted($info->name));
    }

    /**
     * Publishes test suite finished message using SuiteInfo.
     */
    public function suiteFinishedFromInfo(SuiteInfo $info): void
    {
        $this->publish(Formatter::suiteFinished($info->name));
    }

    /**
     * Publishes test batch started as test suite (for DataProvider tests).
     */
    public function batchStartedFromInfo(TestInfo $info): void
    {
        $this->publish(Formatter::suiteStarted($info->name, $info->testDefinition->reflection));
    }

    /**
     * Publishes test batch finished message (for DataProvider tests).
     */
    public function batchFinishedFromInfo(TestInfo $info): void
    {
        $this->publish(Formatter::suiteFinished($info->name));
    }

    /**
     * Handles test suite result.
     *
     * Publishes appropriate TeamCity messages based on suite status.
     */
    public function handleSuiteResult(SuiteInfo $info, SuiteResult $result): void
    {
        // Report suite-level failure if status indicates failure
        if ($result->status->isFailure()) {
            $failedCount = $result->countFailedTests();
            $this->publish(
                Formatter::testStdErr(
                    $info->name,
                    "Test suite failed: {$failedCount} test(s) failed",
                ),
            );
        }

        $this->suiteFinishedFromInfo($info);
    }

    /**
     * Publishes test case started message using CaseInfo.
     *
     * Test case is treated as a suite in TeamCity (a class containing tests).
     */
    public function caseStartedFromInfo(CaseInfo $info): void
    {
        $this->publish(Formatter::suiteStarted($info->name, $info->definition->reflection));
    }

    /**
     * Publishes test case finished message using CaseInfo.
     *
     * Test case is treated as a suite in TeamCity (a class containing tests).
     */
    public function caseFinishedFromInfo(CaseInfo $info): void
    {
        $this->publish(Formatter::suiteFinished($info->name));
    }

    /**
     * Handles test case result.
     *
     * Publishes appropriate TeamCity messages based on case status.
     *
     * @param int<0, max>|null $duration Duration in milliseconds for the case
     */
    public function handleCaseResult(CaseInfo $caseInfo, CaseResult $result, ?int $duration = null): void
    {
        // Report case-level failure if status indicates failure
        if ($result->status->isFailure()) {
            $failedCount = $result->countFailedTests();
            $this->publish(
                Formatter::testStdErr(
                    $caseInfo->name,
                    "Test case failed: {$failedCount} test(s) failed",
                ),
            );
        }

        $this->caseFinishedFromInfo($caseInfo);
    }

    /**
     * Publishes test started message using TestInfo.
     *
     * If the test has DataProvider (MultipleResult), starts it as a test suite.
     */
    public function testStartedFromInfo(TestInfo $info, bool $captureStandardOutput = false, ?string $overrideName = null): void
    {
        $this->publish(Formatter::testStarted($overrideName ?? $info->name, $captureStandardOutput, $info->testDefinition->reflection));
    }

    /**
     * Publishes test finished message using TestInfo.
     *
     * @param int<0, max>|null $duration Duration in milliseconds
     */
    public function testFinishedFromInfo(TestInfo $info, ?int $duration = null): void
    {
        $this->publish(Formatter::testFinished($info->name, $duration));
    }

    /**
     * Publishes test failed message using TestResult.
     */
    public function testFailedFromResult(TestResult $result): void
    {
        $failure = $result->failure;
        $message = $failure?->getMessage() ?? 'Test failed';

        $assertionHistory = $this->formatAssertionHistory($result);
        $details = $failure !== null ? self::formatThrowable($failure) : '';

        if ($assertionHistory !== '') {
            $details = $assertionHistory . $details;
        }

        $this->publish(
            Formatter::testFailed(
                name: $result->info->name,
                message: $message,
                details: $details,
            ),
        );
    }

    /**
     * Publishes test ignored message using TestInfo.
     *
     * @param non-empty-string $message Optional skip reason
     */
    public function testIgnoredFromInfo(TestInfo $info, string $message = ''): void
    {
        $this->publish(Formatter::testIgnored($info->name, $message));
    }

    /**
     * Handles single test result based on status.
     *
     * @param int<0, max>|null $duration Duration in milliseconds
     * @param non-empty-string|null $overrideName Optional name to override test name
     */
    public function handleSingleTestResult(TestResult $result, ?int $duration = null, ?string $overrideName = null): void
    {
        $name = $overrideName ?? $result->info->name;

        match ($result->status) {
            Status::Passed, Status::Flaky => $this->handlePassedTest($result, $duration, $overrideName),
            Status::Failed, Status::Error => $this->handleFailedTest($result, $duration, $overrideName),
            Status::Skipped => $this->handleSkippedTest($result, $duration, $overrideName),
            Status::Risky => $this->handleRiskyTest($result, $duration, $overrideName),
            Status::Cancelled => $this->handleCancelledTest($result, $duration, $overrideName),
            Status::Aborted => $this->handleAbortedTest($result, $duration, $overrideName),
        };
    }

    private static function key(string $name): string
    {
        return "\033[36;1m{$name}:\033[0m ";
    }

    private static function dim(string $text): string
    {
        return "\033[2m{$text}\033[0m";
    }

    /**
     * Handles passed test status.
     *
     * @param non-empty-string|null $overrideName Optional name to override test name
     */
    private function handlePassedTest(TestResult $result, ?int $duration, ?string $overrideName = null): void
    {
        $name = $overrideName ?? $result->info->name;

        $assertionHistory = $this->formatAssertionHistory($result);
        if ($assertionHistory !== '') {
            $this->publish(Formatter::testStdOut($name, $assertionHistory));
        }

        $this->publish(Formatter::testFinished($name, $duration));
    }

    /**
     * Handles skipped test status.
     *
     * @param non-empty-string|null $overrideName Optional name to override test name
     */
    private function handleSkippedTest(TestResult $result, ?int $duration, ?string $overrideName = null): void
    {
        $name = $overrideName ?? $result->info->name;
        $this->publish(Formatter::testIgnored($name));
        $this->publish(Formatter::testFinished($name, $duration));
    }

    /**
     * Handles cancelled test status.
     *
     * @param non-empty-string|null $overrideName Optional name to override test name
     */
    private function handleCancelledTest(TestResult $result, ?int $duration, ?string $overrideName = null): void
    {
        $name = $overrideName ?? $result->info->name;
        $this->publish(Formatter::testIgnored($name, 'Test cancelled'));
        $this->publish(Formatter::testFinished($name, $duration));
    }

    /**
     * Handles failed test status.
     *
     * @param non-empty-string|null $overrideName Optional name to override test name
     */
    private function handleFailedTest(TestResult $result, ?int $duration, ?string $overrideName = null): void
    {
        $name = $overrideName ?? $result->info->name;
        $failure = $result->failure;
        $message = $failure?->getMessage() ?? 'Test failed';

        $assertionHistory = $this->formatAssertionHistory($result);
        $details = $failure !== null ? self::formatThrowable($failure) : '';

        if ($assertionHistory !== '') {
            $details = $assertionHistory . $details;
        }

        $this->publish(
            Formatter::testFailed(
                name: $name,
                message: $message,
                details: $details,
            ),
        );
        $this->publish(Formatter::testFinished($name, $duration));
    }

    /**
     * Handles aborted test status.
     *
     * @param non-empty-string|null $overrideName Optional name to override test name
     */
    private function handleAbortedTest(TestResult $result, ?int $duration, ?string $overrideName = null): void
    {
        $name = $overrideName ?? $result->info->name;

        $assertionHistory = $this->formatAssertionHistory($result);
        $details = $result->failure !== null ? self::formatThrowable($result->failure) : '';

        if ($assertionHistory !== '') {
            $details = $assertionHistory . $details;
        }

        $this->publish(
            Formatter::testFailed(
                $name,
                'Test aborted',
                $details,
            ),
        );
        $this->publish(Formatter::testFinished($name, $duration));
    }

    /**
     * Handles risky test status.
     *
     * @param non-empty-string|null $overrideName Optional name to override test name
     */
    private function handleRiskyTest(TestResult $result, ?int $duration, ?string $overrideName = null): void
    {
        $name = $overrideName ?? $result->info->name;

        $assertionHistory = $this->formatAssertionHistory($result);
        if ($assertionHistory !== '') {
            $this->publish(Formatter::testStdOut($name, $assertionHistory));
        }

        $this->publish(
            Formatter::testStdOut(
                $name,
                'Warning: This test has been marked as risky',
            ),
        );
        $this->publish(Formatter::testFinished($name, $duration));
    }

    /**
     * Formats assertion history for TeamCity output.
     *
     * Returns a formatted string with assertion history or empty string if no history available.
     */
    private function formatAssertionHistory(TestResult $result): string
    {
        $testState = $result->getAttribute(TestState::class);

        if ($testState === null) {
            return '';
        }

        if ($testState->history === []) {
            return "Assertion History:\n  No assertions were made.\n\n";
        }

        $output = "Assertion History:\n";

        foreach ($testState->history as $assertion) {
            $output .= $this->formatAssertionLine($assertion);
        }

        return $output . "\n";
    }

    /**
     * Formats a single assertion line for TeamCity output.
     *
     * @param int<0, max> $level Indentation level for nested assertions
     */
    private function formatAssertionLine(Record $assertion, int $level = 0): string
    {
        $indent = \str_repeat('  ', $level);
        $symbol = $assertion->isSuccess() ? '✓' : '✗';

        $text = (string) $assertion;
        $context = $assertion->getContext();
        if ($context !== '') {
            $text = $text . ' → ' . $context;
        }

        $output = "{$indent}  {$symbol} {$text}\n";

        if ($assertion instanceof CompositeRecord) {
            foreach ($assertion->getRecords() as $record) {
                if (!$record->isSuccess()) {
                    $output .= $this->formatAssertionLine($record, $level + 1);
                }
            }
        }

        return $output;
    }

    /**
     * Publishes a TeamCity service message to stdout.
     *
     * @param non-empty-string $message Formatted TeamCity message
     */
    private function publish(string $message): void
    {
        echo $message . "\n";
    }
}
