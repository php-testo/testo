<?php

declare(strict_types=1);

namespace Testo\Output\Teamcity\Teamcity;

use Testo\Assert\State\Assertion\ComparisonFailure;
use Testo\Common\Environment;
use Testo\Common\Info;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\SuiteInfo;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Log\Message;
use Testo\Core\Value\Status;
use Testo\Output\Rendering\ChannelRenderer;
use Testo\Output\Rendering\StackTrace;

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
     * Channel routed to TeamCity's stderr stream; every other channel goes to stdout.
     */
    private const CHANNEL_STDERR = 'stderr';

    /** @var resource */
    private $output;

    private readonly ChannelRenderer $channels;

    /**
     * @param resource|null $output Stream the logger writes to; defaults to {@see \STDOUT}.
     */
    public function __construct($output = null)
    {
        $this->output = $output ?? \STDOUT;
        $this->channels = new ChannelRenderer();
    }

    /**
     * Formats a throwable into a detailed string with class, message, file, line, and stack trace.
     */
    public static function formatThrowable(
        \Throwable $throwable,
        ?\ReflectionFunctionAbstract $boundary = null,
    ): string {
        $parts = [];
        $current = $throwable;

        do {
            $class = $current::class;
            $file = $current->getFile();
            $line = $current->getLine();
            $trace = self::formatTrace(StackTrace::cutStackTrace($current->getTrace(), $boundary, false));

            $parts[] = "{$class}\nFile: {$file}:{$line}\n\nStack trace:\n{$trace}";
        } while ($current = $current->getPrevious());

        return \implode("\n\nCaused by:\n", $parts);
    }

    /**
     * Publishes environment information as a TeamCity block with plain text lines.
     */
    public function logEnvironment(): void
    {
        $this->publish("\033[1m" . Info::NAME . "\033[0m" . self::dim(' v' . Info::version()));

        $this->publish(Formatter::blockOpened('Environment'));

        $this->publish(self::key('PHP') . \sprintf(
            '%s %s (%s, memory: %s)',
            Environment::getPhpVersion(),
            Environment::getThread(),
            \PHP_SAPI,
            \ini_get('memory_limit') ?: 'unlimited',
        ));

        $modes = Environment::getXDebugMode();
        $xdebug = match (true) {
            !Environment::hasXDebug() => self::dim('off'),
            $modes !== [] => Environment::getXDebugVersion() . self::dim(' (' . \implode(', ', $modes) . ')'),
            default => Environment::getXDebugVersion() . self::dim(' (off)'),
        };
        $this->publish('  ' . self::key('XDebug') . $xdebug);

        $opcache = match (true) {
            !Environment::isOpCacheEnabled() => self::dim('off'),
            Environment::isJitEnabled() => 'enabled with JIT',
            default => 'enabled',
        };
        $this->publish('  ' . self::key('OPcache') . $opcache);

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
    public function testStartedFromInfo(TestInfo $info, bool $captureStandardOutput = false, ?string $overrideName = null, ?string $locationSuffix = null): void
    {
        // New test: reset channel grouping so its first message prints a fresh channel header.
        $this->channels->reset();

        $this->publish(Formatter::testStarted($overrideName ?? $info->name, $captureStandardOutput, $info->testDefinition->reflection, $locationSuffix));
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

        $details = $failure !== null
            ? self::formatThrowable($failure, $result->info->testDefinition->reflection)
            : '';

        $isComparison = $failure instanceof ComparisonFailure;

        $this->publish(
            Formatter::testFailed(
                name: $result->info->name,
                message: $message,
                details: $details,
                type: $isComparison ? 'comparisonFailure' : null,
                expected: $isComparison ? $failure->getExpectedAsString() : null,
                actual: $isComparison ? $failure->getActualAsString() : null,
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
     * Publishes a captured {@see Message} as a stdout/stderr service message for the given test.
     *
     * The channel decides the stream: the dedicated `stderr` channel maps to TeamCity's stderr,
     * everything else to stdout. Severity travels separately as the `level` attribute. Consecutive
     * messages from the same channel are appended verbatim; when the channel changes, a colored
     * channel header is printed on its own line first. Must be emitted between this test's
     * `testStarted` and `testFinished` to nest correctly.
     *
     * @param non-empty-string $name Test name the message belongs to.
     */
    public function logMessage(string $name, Message $message): void
    {
        if ($message->content === '') {
            return;
        }

        $out = $this->channels->render($message);

        # Machine-readable metadata for consumers that understand it; standard parsers ignore it.
        $attributes = [
            'channel' => $message->channel,
            'level' => $message->level->value,
        ];

        $this->publish($message->channel === self::CHANNEL_STDERR
            ? Formatter::testStdErr($name, $out, $attributes)
            : Formatter::testStdOut($name, $out, $attributes));
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

    /**
     * @param list<array<string, mixed>> $trace
     */
    private static function formatTrace(array $trace): string
    {
        $lines = [];

        foreach ($trace as $i => $frame) {
            $location = isset($frame['file'])
                ? "{$frame['file']}({$frame['line']})"
                : '[internal function]';
            $call = isset($frame['class'])
                ? "{$frame['class']}{$frame['type']}{$frame['function']}()"
                : "{$frame['function']}()";
            $lines[] = "#{$i} {$location}: {$call}";
        }

        return \implode("\n", $lines);
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

        $details = $failure !== null
            ? self::formatThrowable($failure, $result->info->testDefinition->reflection)
            : '';

        $isComparison = $failure instanceof ComparisonFailure;

        $this->publish(
            Formatter::testFailed(
                name: $name,
                message: $message,
                details: $details,
                type: $isComparison ? 'comparisonFailure' : null,
                expected: $isComparison ? $failure->getExpectedAsString() : null,
                actual: $isComparison ? $failure->getActualAsString() : null,
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

        $details = $result->failure !== null
            ? self::formatThrowable($result->failure, $result->info->testDefinition->reflection)
            : '';

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

        $this->publish(
            Formatter::testStdOut(
                $name,
                "\nWarning: This test has been marked as risky",
            ),
        );
        $this->publish(Formatter::testFinished($name, $duration));
    }

    /**
     * Writes a TeamCity message (or plain line) to the output stream, newline-terminated.
     *
     * Writes straight to the stream (default {@see \STDOUT}), bypassing PHP output buffering: the
     * messenger's output interceptor captures `echo`/`print` (the `php://output` layer), but
     * TeamCity messages must reach the real stdout untouched, and `ob_*` does not intercept stream
     * writes.
     *
     * @param non-empty-string $message Formatted TeamCity message
     */
    private function publish(string $message): void
    {
        \fwrite($this->output, $message . "\n");
    }
}
