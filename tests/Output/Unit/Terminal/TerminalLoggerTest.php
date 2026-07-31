<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Terminal;

use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\RunResult;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Common\Messenger;
use Testo\Core\Log\Level;
use Testo\Core\Log\Message;
use Testo\Core\Log\MessageLog;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Core\Value\Verbosity;
use Testo\Output\Terminal\Renderer\OutputFormat;
use Testo\Output\Terminal\Renderer\TerminalLogger;
use Testo\Test;
use Tests\Output\Stub\JUnit\SampleTestClass;

#[Test]
#[Covers(TerminalLogger::class)]
final class TerminalLoggerTest
{
    public function singleTestRunSurfacesCapturedChannelOutput(): void
    {
        $test = self::test('passingTest', Status::Passed, messages: new MessageLog([
            new Message(0.0, 'stdout', Level::Info, 'hello single test'),
        ]));

        $output = self::render(self::run([$test], Status::Passed), handled: [$test]);

        Assert::string($output)->contains('Output:');
        Assert::string($output)->contains('[stdout]');
        Assert::string($output)->contains('hello single test');
    }

    public function multiTestRunDoesNotSurfacePerTestChannelOutput(): void
    {
        $first = self::test('passingTest', Status::Passed, messages: new MessageLog([
            new Message(0.0, 'stdout', Level::Info, 'first test output'),
        ]));
        $second = self::test('passingTest', Status::Passed, messages: new MessageLog([
            new Message(0.0, 'stdout', Level::Info, 'second test output'),
        ]));
        $run = self::run([$first, $second], Status::Passed, summary: new Summary(['Passed' => 2]));

        $output = self::render($run, handled: [$first, $second]);

        Assert::string($output)->notContains('first test output');
        Assert::string($output)->notContains('second test output');
    }

    public function stderrChannelIsWrittenToTheErrorStreamNotStdout(): void
    {
        $stdout = \fopen('php://memory', 'rb+');
        $stderr = \fopen('php://memory', 'rb+');
        \assert($stdout !== false && $stderr !== false);

        try {
            $logger = new TerminalLogger(OutputFormat::Compact, Verbosity::Normal, $stdout, $stderr);
            $logger->logMessage(new Message(0.0, Messenger::CHANNEL_STDERR, Level::Error, 'framework boom'));

            \rewind($stdout);
            \rewind($stderr);
            $out = (string) \stream_get_contents($stdout);
            $err = (string) \stream_get_contents($stderr);
        } finally {
            \fclose($stdout);
            \fclose($stderr);
        }

        // The framework fault must land on the real error stream verbatim (no channel header/coloring),
        // never on the structured stdout report.
        Assert::same($err, "framework boom\n");
        Assert::same($out, '');
    }

    public function verboseSingleTestRunDoesNotAppendOutput(): void
    {
        $test = self::test('passingTest', Status::Passed, messages: new MessageLog([
            new Message(0.0, 'stdout', Level::Info, 'streamed live already'),
        ]));

        // At Verbose+ the output was already streamed live during the run, so the summary must not repeat it.
        $output = self::render(self::run([$test], Status::Passed), handled: [$test], verbosity: Verbosity::Verbose);

        Assert::string($output)->notContains('streamed live already');
    }

    public function singleFailedTestOutputIsNotDuplicated(): void
    {
        $failed = self::test('failingTest', Status::Failed, new \RuntimeException('boom'), new MessageLog([
            new Message(0.0, 'stdout', Level::Info, 'boom output'),
        ]));
        $run = self::run([$failed], Status::Failed, summary: new Summary(['Failed' => 1]));

        // printFailures() already owns a failed test's output; the single-test surfacing must stay
        // silent so the captured output is not printed twice.
        $output = self::render($run, handled: [$failed]);

        Assert::same(\substr_count($output, 'boom output'), 1);
    }

    public function dataSetIsTreatedAsAnOrdinarySingleTest(): void
    {
        // The logger never sees the data-provider batch — only each data set's own result flows through
        // handleTestResult(). So a single-data-set run is just a single handled result and its output
        // is surfaced like any other single test, no MultipleResult unwrapping needed.
        $dataSet = self::test('passingTest', Status::Passed, messages: new MessageLog([
            new Message(0.0, 'stdout', Level::Info, 'single dataset output'),
        ]));

        $output = self::render(self::run([$dataSet], Status::Passed), handled: [$dataSet]);

        Assert::string($output)->contains('single dataset output');
    }

    public function dataProviderDescriptionIsPrintedOnceAtTheBatchNode(): void
    {
        // A DataProvider test's description belongs to the method, not to each dataset — the datasets
        // carry the same 'description' attribute the runner copies onto every result. It must appear
        // once under the batch node and never repeat under the individual datasets.
        $description = 'Sample description line.';
        $first = self::test('describedTest', Status::Passed, attributes: ['description' => $description]);
        // Data sets of one batch are derived with TestInfo::with(), so they share the batch's info —
        // and its identity, which is what the logger keys the batch indentation by.
        $second = self::test(
            'describedTest',
            Status::Passed,
            attributes: ['description' => $description],
            info: $first->info,
        );

        $output = self::renderBatch($first->info, [
            'Dataset #0 [0]' => $first,
            'Dataset #1 [1]' => $second,
        ]);

        Assert::same(\substr_count($output, $description), 1);
    }

    public function regularTestStillPrintsItsDescription(): void
    {
        // A test without a DataProvider has no batch node, so its description must still print under
        // the test itself.
        $description = 'Sample description line.';
        $test = self::test('describedTest', Status::Passed, attributes: ['description' => $description]);

        $output = self::render(self::run([$test], Status::Passed), handled: [$test]);

        Assert::string($output)->contains($description);
    }

    public function interleavedBatchesEachReportUnderTheirOwnDataSetName(): void
    {
        $first = self::test('passingTest', Status::Passed);
        $second = self::test('failingTest', Status::Passed);

        $output = self::capture(static function (TerminalLogger $logger) use ($first, $second): void {
            $logger->batchStartedFromInfo($first->info);
            $logger->batchStartedFromInfo($second->info);
            $logger->testStartedFromInfo($first->info, 'Dataset #0 [a]');
            $logger->testStartedFromInfo($second->info, 'Dataset #0 [b]');
            // Out of order on purpose: the second batch's data set reports first.
            $logger->handleTestResult($second, 0);
            $logger->handleTestResult($first, 0);
            $logger->closeTest($first->info);
            $logger->closeTest($second->info);
        });

        // A single "current test" slot would give both data sets the name of whichever batch announced
        // its data set last, and lose the other one entirely.
        Assert::string($output)->contains('Dataset #0 [a]');
        Assert::string($output)->contains('Dataset #0 [b]');
    }

    public function aTestIsNotIndentedByAnInterleavedBatch(): void
    {
        $batch = self::test('passingTest', Status::Passed);
        $regular = self::test('failingTest', Status::Passed);

        $alone = self::capture(static fn(TerminalLogger $logger) => $logger->handleTestResult($regular, 0));
        $besideBatch = self::capture(static function (TerminalLogger $logger) use ($batch, $regular): void {
            $logger->batchStartedFromInfo($batch->info);
            $logger->handleTestResult($regular, 0);
            $logger->closeTest($batch->info);
            $logger->closeTest($regular->info);
        });

        // The batch's indentation belongs to the batch. A test finishing while someone else's batch is
        // open must render exactly as it would on its own — compared line-for-line, since the indented
        // form merely *contains* the unindented one.
        Assert::same(self::lastLine($besideBatch), self::lastLine($alone));
    }

    /**
     * Last non-empty line of the rendered output, for comparing one report line exactly.
     */
    private static function lastLine(string $output): string
    {
        $lines = \array_filter(\explode("\n", $output), static fn(string $line): bool => $line !== '');
        \assert($lines !== []);

        return (string) \end($lines);
    }

    /**
     * Drives the callback against a logger writing to an in-memory stream and returns what it wrote.
     * For scenarios whose event order matters — {@see render()} and {@see renderBatch()} cover the
     * fixed sequential ones.
     *
     * @param \Closure(TerminalLogger): void $scenario
     */
    private static function capture(\Closure $scenario): string
    {
        $stream = \fopen('php://memory', 'rb+');
        \assert($stream !== false);

        try {
            $scenario(new TerminalLogger(OutputFormat::Compact, Verbosity::Normal, $stream));
            \rewind($stream);
            $output = \stream_get_contents($stream);
        } finally {
            \fclose($stream);
        }

        return $output === false ? '' : $output;
    }

    /**
     * Feeds each handled result through {@see TerminalLogger::handleTestResult()} (as the plugin does
     * for every test / data set), then prints the summary into an in-memory stream and returns what
     * was written.
     *
     * @param list<TestResult> $handled Leaf results the live event flow would have reported, in order.
     */
    private static function render(
        RunResult $run,
        array $handled = [],
        Verbosity $verbosity = Verbosity::Normal,
    ): string {
        $stream = \fopen('php://memory', 'rb+');
        \assert($stream !== false);

        try {
            $logger = new TerminalLogger(OutputFormat::Compact, $verbosity, $stream);
            foreach ($handled as $result) {
                $logger->handleTestResult($result, 0);
            }
            $logger->printSummary($run);
            \rewind($stream);
            $output = \stream_get_contents($stream);
        } finally {
            \fclose($stream);
        }

        return $output === false ? '' : $output;
    }

    /**
     * Drives the logger through a DataProvider batch the way {@see \Testo\Output\Terminal\TerminalPlugin}
     * does — a batch node followed by each dataset keyed by its display name — and returns what was
     * written.
     *
     * @param array<non-empty-string, TestResult> $datasets Dataset display name => result, in order.
     */
    private static function renderBatch(TestInfo $info, array $datasets): string
    {
        $stream = \fopen('php://memory', 'rb+');
        \assert($stream !== false);

        try {
            $logger = new TerminalLogger(OutputFormat::Compact, Verbosity::Normal, $stream);
            $logger->batchStartedFromInfo($info);
            foreach ($datasets as $name => $result) {
                $logger->testStartedFromInfo($info, $name);
                $logger->handleTestResult($result, 0);
            }
            $logger->batchFinishedFromInfo($info);
            \rewind($stream);
            $output = \stream_get_contents($stream);
        } finally {
            \fclose($stream);
        }

        return $output === false ? '' : $output;
    }

    /**
     * Wraps the given test results in a single suite/case.
     *
     * @param list<TestResult> $results
     */
    private static function run(array $results, Status $status, ?Summary $summary = null): RunResult
    {
        $summary ??= new Summary(['Passed' => 1]);
        $case = new CaseResult($results, $status, $summary);
        $suite = new SuiteResult([$case], $status, $summary);

        return new RunResult([$suite], $status, 0.0, $summary);
    }

    /**
     * @param array<non-empty-string, mixed> $attributes
     */
    private static function test(
        string $method,
        Status $status,
        ?\Throwable $failure = null,
        ?MessageLog $messages = null,
        array $attributes = [],
        ?TestInfo $info = null,
    ): TestResult {
        $info ??= new TestInfo(
            name: $method,
            caseInfo: new CaseInfo(
                suiteIdentity: new SuiteIdentity('Output/Unit'),
                definition: new CaseDefinition(
                    name: SampleTestClass::class,
                    type: 'test',
                    file: Path::create(__FILE__),
                    reflection: new \ReflectionClass(SampleTestClass::class),
                ),
            ),
            testDefinition: new TestDefinition(new \ReflectionMethod(SampleTestClass::class, $method)),
        );

        return new TestResult(
            info: $info,
            status: $status,
            failure: $failure,
            attributes: $attributes,
            messages: $messages ?? new MessageLog(),
        );
    }
}
