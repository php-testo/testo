<?php

declare(strict_types=1);

namespace Testo\Output\Teamcity\Teamcity;

use Testo\Assert\State\Assertion\ComparisonFailure;
use Testo\Common\Environment;
use Testo\Common\Info;
use Testo\Common\Messenger;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\SuiteInfo;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Context\Identity\TestIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Log\Message;
use Testo\Core\Value\Status;
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
    private const CHANNEL_STDERR = Messenger::CHANNEL_STDERR;

    /** @var resource */
    private $output;

    /**
     * @param resource|null $output Stream the logger writes to; defaults to {@see \STDOUT}.
     */
    public function __construct($output = null)
    {
        $this->output = $output ?? \STDOUT;
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
            $message = $current->getMessage();
            $file = $current->getFile();
            $line = $current->getLine();
            $trace = self::formatTrace(StackTrace::cutStackTrace($current->getTrace(), $boundary, false));

            $header = $message !== '' ? "{$class}: {$message}" : $class;
            $parts[] = "{$header}\nFile: {$file}:{$line}\n\nStack trace:\n{$trace}";
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
     *
     * Announces the suite's size first, so an IDE can size its progress bar before the first test
     * reports. The count is the number of located tests: a DataProvider test counts once here but
     * reports one node per data set, so it is a lower bound rather than an exact total.
     */
    public function suiteStartedFromInfo(SuiteInfo $info): void
    {
        $count = 0;
        foreach ($info->testCases->getCases() as $case) {
            $count += \count($case->tests->getTests());
        }

        $count > 0 and $this->publish(Formatter::testCount($count));

        $this->publish(Formatter::suiteStarted($info->name, $info->identity));
    }

    /**
     * Publishes test suite finished message using SuiteInfo.
     */
    public function suiteFinishedFromInfo(SuiteInfo $info, ?Status $status = null): void
    {
        $this->publish(Formatter::suiteFinished($info->name, $info->identity, $status));
    }

    /**
     * Publishes test batch started as test suite (for DataProvider tests).
     */
    public function batchStartedFromInfo(TestInfo $info): void
    {
        $this->publish(Formatter::suiteStarted($info->name, $info->identity));
    }

    /**
     * Publishes test batch finished message (for DataProvider tests).
     */
    public function batchFinishedFromInfo(TestInfo $info, ?Status $status = null): void
    {
        $this->publish(Formatter::suiteFinished($info->name, $info->identity, $status));
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
            $failedCount = $result->summary->failed();
            $this->publish(
                Formatter::testStdErr(
                    $info->name,
                    "Test suite failed: {$failedCount} test(s) failed",
                    identity: $info->identity,
                ),
            );
        }

        $this->suiteFinishedFromInfo($info, $result->status);
    }

    /**
     * Publishes test case started message using CaseInfo.
     *
     * Test case is treated as a suite in TeamCity (a class containing tests).
     */
    public function caseStartedFromInfo(CaseInfo $info): void
    {
        $this->publish(Formatter::suiteStarted($info->name, $info->identity));
    }

    /**
     * Publishes test case finished message using CaseInfo.
     *
     * Test case is treated as a suite in TeamCity (a class containing tests).
     */
    public function caseFinishedFromInfo(CaseInfo $info, ?Status $status = null): void
    {
        $this->publish(Formatter::suiteFinished($info->name, $info->identity, $status));
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
            $failedCount = $result->summary->failed();
            $this->publish(
                Formatter::testStdErr(
                    $caseInfo->name,
                    "Test case failed: {$failedCount} test(s) failed",
                    identity: $caseInfo->identity,
                ),
            );
        }

        $this->caseFinishedFromInfo($caseInfo, $result->status);
    }

    /**
     * Publishes test started message using TestInfo.
     *
     * If the test has DataProvider (MultipleResult), starts it as a test suite.
     */
    public function testStartedFromInfo(TestInfo $info, bool $captureStandardOutput = false, ?string $overrideName = null): void
    {
        $description = $info->testDefinition->getDescription();
        $description === '' and $description = null;

        $this->publish(Formatter::testStarted(
            $overrideName ?? $info->name,
            $captureStandardOutput,
            $info->identity,
            $description,
        ));
    }

    /**
     * Publishes test finished message using TestInfo.
     *
     * @param int<0, max>|null $duration Duration in milliseconds
     */
    public function testFinishedFromInfo(TestInfo $info, ?int $duration = null): void
    {
        $this->publish(Formatter::testFinished($info->name, $duration, $info->identity));
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
                identity: $result->info->identity,
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
        $this->publish(Formatter::testIgnored($info->name, $message, $info->identity));
    }

    /**
     * Publishes a captured {@see Message} as a stdout/stderr service message for the given test.
     *
     * The channel decides the stream: the dedicated `stderr` channel maps to TeamCity's stderr,
     * everything else to stdout. The content is emitted verbatim — no `[channel] <time>` header is
     * printed; the channel, severity and timestamp travel as the machine-readable `channel`, `level`
     * and `time` attributes instead. Must be emitted between this test's `testStarted` and
     * `testFinished` to nest correctly.
     *
     * @param non-empty-string $name Test name the message belongs to.
     * @param TestIdentity|null $identity Address of the test the output came from, so interleaved output
     *        lands on the right node instead of wherever the stream happens to be.
     */
    public function logMessage(string $name, Message $message, ?TestIdentity $identity = null): void
    {
        if ($message->content === '') {
            return;
        }

        # Machine-readable metadata for consumers that understand it; standard parsers ignore it.
        $attributes = [
            'channel' => $message->channel,
            'level' => $message->level->value,
            'time' => (string) $message->time,
        ];

        $this->publish($message->channel === self::CHANNEL_STDERR
            ? Formatter::testStdErr($name, $message->content, $attributes, $identity)
            : Formatter::testStdOut($name, $message->content, $attributes, $identity));
    }

    /**
     * Publishes a {@see Message} that belongs to no test — e.g. an internal error raised outside any
     * running test (suite setup, bootstrap, a faulty listener between tests).
     *
     * Emitted as a standalone TeamCity `message` service message: the dedicated `stderr` channel maps
     * to `ERROR` status, everything else to `NORMAL`. This keeps framework-level faults visible even
     * when there is no test to attribute them to.
     */
    public function logStandaloneMessage(Message $message): void
    {
        if ($message->content === '') {
            return;
        }

        $this->publish(Formatter::message(
            $message->content,
            $message->channel === self::CHANNEL_STDERR ? 'ERROR' : 'NORMAL',
        ));
    }

    /**
     * Reports a run that executed no tests as a TeamCity build problem, so CI fails the build
     * instead of treating a test-free run as a green success.
     */
    public function logEmptyRun(): void
    {
        $this->publish(Formatter::buildProblem('No tests were executed', 'testo.noTests'));
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

    /**
     * How many assertions the test performed, or `null` when nothing counted them — the metric is
     * contributed by the Assert plugin, and a suite running without it says nothing rather than zero.
     *
     * @return int<0, max>|null
     */
    private static function assertionsOf(TestResult $result): ?int
    {
        return $result->summary->metrics['assertions'] ?? null;
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

        $this->publish(Formatter::testFinished(
            $name,
            $duration,
            $result->info->identity,
            $result->status,
            self::assertionsOf($result),
        ));
    }

    /**
     * Handles skipped test status.
     *
     * @param non-empty-string|null $overrideName Optional name to override test name
     */
    private function handleSkippedTest(TestResult $result, ?int $duration, ?string $overrideName = null): void
    {
        $name = $overrideName ?? $result->info->name;
        $identity = $result->info->identity;
        $this->publish(Formatter::testIgnored($name, $result->failure?->getMessage() ?? '', $identity));
        $this->publish(Formatter::testFinished($name, $duration, $identity, $result->status));
    }

    /**
     * Handles cancelled test status.
     *
     * @param non-empty-string|null $overrideName Optional name to override test name
     */
    private function handleCancelledTest(TestResult $result, ?int $duration, ?string $overrideName = null): void
    {
        $name = $overrideName ?? $result->info->name;
        $identity = $result->info->identity;
        $message = $result->failure?->getMessage() ?? '';
        $this->publish(Formatter::testIgnored($name, $message === '' ? 'Test cancelled' : $message, $identity));
        $this->publish(Formatter::testFinished($name, $duration, $identity, $result->status));
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
        $identity = $result->info->identity;

        $this->publish(
            Formatter::testFailed(
                name: $name,
                message: $message,
                details: $details,
                type: $isComparison ? 'comparisonFailure' : null,
                expected: $isComparison ? $failure->getExpectedAsString() : null,
                actual: $isComparison ? $failure->getActualAsString() : null,
                identity: $identity,
            ),
        );
        $this->publish(Formatter::testFinished($name, $duration, $identity, $result->status));
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
        $identity = $result->info->identity;

        $this->publish(
            Formatter::testFailed(
                $name,
                'Test aborted',
                $details,
                identity: $identity,
            ),
        );
        $this->publish(Formatter::testFinished($name, $duration, $identity, $result->status));
    }

    /**
     * Handles risky test status.
     *
     * @param non-empty-string|null $overrideName Optional name to override test name
     */
    private function handleRiskyTest(TestResult $result, ?int $duration, ?string $overrideName = null): void
    {
        $name = $overrideName ?? $result->info->name;
        $identity = $result->info->identity;

        $this->publish(
            Formatter::testStdOut(
                $name,
                "\nWarning: This test has been marked as risky",
                identity: $identity,
            ),
        );
        $this->publish(Formatter::testFinished($name, $duration, $identity, $result->status));
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
