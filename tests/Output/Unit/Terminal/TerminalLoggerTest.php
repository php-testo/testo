<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Terminal;

use Internal\Path;
use Testo\Assert;
use Testo\Assert\State\Assertion\ComparisonFailure;
use Testo\Codecov\Covers;
use Testo\Common\Info;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\RunResult;
use Testo\Core\Context\SuiteInfo;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\CaseDefinitions;
use Testo\Core\Definition\TestDefinition;
use Testo\Common\Messenger;
use Testo\Core\Log\Level;
use Testo\Core\Log\Message;
use Testo\Core\Log\MessageLog;
use Testo\Core\Report\ReportInfo;
use Testo\Core\Value\RunTiming;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Core\Value\Verbosity;
use Testo\Data\MultipleResult;
use Testo\Output\Terminal\Renderer\OutputFormat;
use Testo\Output\Terminal\Renderer\Style;
use Testo\Output\Terminal\Renderer\TerminalLogger;
use Testo\Data\DataSet;
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

    public function sequentialRunReportsThePipelineOverheadAroundTheTests(): void
    {
        $test = self::test('passingTest', Status::Passed);
        $run = self::run(
            [$test],
            Status::Passed,
            summary: new Summary(['Passed' => 1], duration: 1.0),
            // Discovery is deliberately the largest phase: it is a phase of its own and must not be
            // counted as overhead, which is what subtracting from the whole loop used to do.
            timing: new RunTiming(startup: 0.5, discovery: 4.0, tests: 1.5, teardown: 0.5),
        );

        $output = self::render($run, handled: [$test]);

        // 1.5 s in the tests phase against 1.00 s declared by the tests themselves.
        Assert::string($output)->contains('1.00s tests · 500ms overhead · 6.50s total');
    }

    public function concurrentRunStatesTheWallItTookInsteadOfAZeroOverhead(): void
    {
        $test = self::test('passingTest', Status::Passed);
        $run = self::run(
            [$test],
            Status::Passed,
            summary: new Summary(['Passed' => 1], duration: 8.0),
            timing: new RunTiming(startup: 0.5, discovery: 1.0, tests: 4.0, teardown: 0.5),
        );

        $output = self::render($run, handled: [$test]);

        // Overlapping tests declare more time than the wall they ran on, so the difference is not
        // overhead — separating the two needs interval data the terminal never recorded. It states the
        // wall rather than reporting an overhead of zero, which is what clamping at zero used to print.
        Assert::string($output)->contains('8.00s tests · 4.00s wall · 6.00s total');
        Assert::string($output)->notContains('overhead');
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

    public function suiteAndCaseLifecycleRendersHeadersAndSummaries(): void
    {
        $test = self::test('passingTest', Status::Passed);
        $caseInfo = $test->info->caseInfo;
        $suiteInfo = new SuiteInfo('Output/Unit', CaseDefinitions::fromArray());
        $summary = new Summary(['Passed' => 1]);
        $caseResult = new CaseResult([$test], Status::Passed, $summary);
        $suiteResult = new SuiteResult([$caseResult], Status::Passed, $summary);

        // Verbose is the only mode where the case footer/summary carry visible text, so the lifecycle
        // writes are all observable in one pass.
        $output = self::capture(
            static function (TerminalLogger $logger) use ($suiteInfo, $caseInfo, $caseResult, $suiteResult): void {
                $logger->suiteStartedFromInfo($suiteInfo);
                $logger->caseStartedFromInfo($caseInfo);
                $logger->handleCaseResult($caseInfo, $caseResult);
                $logger->handleSuiteResult($suiteInfo, $suiteResult);
            },
            format: OutputFormat::Verbose,
        );

        Assert::string($output)
            ->contains('Suite: Output/Unit')
            ->contains('Case:')
            ->contains('Summary:')
            ->contains('1 passed');
    }

    #[DataSet([Status::Skipped, '○'], 'skipped renders the open circle')]
    #[DataSet([Status::Cancelled, '○'], 'cancelled reuses the skipped circle')]
    #[DataSet([Status::Risky, '?'], 'risky renders the question mark')]
    public function finishedTestRendersItsStatusSymbol(Status $status, string $symbol): void
    {
        $test = self::test('passingTest', $status);

        $output = self::withoutColors(static fn(): string => self::capture(
            static fn(TerminalLogger $logger) => $logger->handleTestResult($test, 0),
        ));

        Assert::string($output)->contains("{$symbol} passingTest");
    }

    public function verboseRunStreamsAnOwnedTestsChannelOutputLive(): void
    {
        $test = self::test('passingTest', Status::Passed);
        $id = $test->info->identity->pipelineId;

        $output = self::capture(
            static fn(TerminalLogger $logger) => $logger->logMessage(
                new Message(0.0, 'stdout', Level::Info, "streamed live\n"),
                $id,
            ),
            verbosity: Verbosity::Verbose,
        );

        Assert::string($output)->contains('[stdout]')->contains('streamed live');
    }

    public function verboseRunStreamsUnownedChannelOutputThroughTheSharedGroup(): void
    {
        $output = self::capture(
            static fn(TerminalLogger $logger) => $logger->logMessage(
                new Message(0.0, 'stdout', Level::Info, "unowned line\n"),
                null,
            ),
            verbosity: Verbosity::Verbose,
        );

        Assert::string($output)->contains('[stdout]')->contains('unowned line');
    }

    public function channelOutputStaysSilentWhenThereIsNothingToStream(): void
    {
        // Empty content is dropped before anything is written.
        $empty = self::capture(
            static fn(TerminalLogger $logger) => $logger->logMessage(
                new Message(0.0, 'stdout', Level::Info, ''),
                1,
            ),
            verbosity: Verbosity::Verbose,
        );
        Assert::same($empty, '');

        // A non-empty channel message is suppressed below Verbose (nothing streams live at Normal).
        $normal = self::capture(
            static fn(TerminalLogger $logger) => $logger->logMessage(
                new Message(0.0, 'stdout', Level::Info, 'noise'),
                1,
            ),
        );
        Assert::same($normal, '');
    }

    public function resetChannelsReopensTheChannelHeaderForTheNextMessage(): void
    {
        $test = self::test('passingTest', Status::Passed);
        $info = $test->info;
        $id = $info->identity->pipelineId;

        $output = self::capture(
            static function (TerminalLogger $logger) use ($info, $id): void {
                $logger->logMessage(new Message(0.0, 'stdout', Level::Info, "first\n"), $id);
                // Without the reset the second same-channel message would append without a header.
                $logger->resetChannels($info);
                $logger->logMessage(new Message(0.0, 'stdout', Level::Info, "second\n"), $id);
            },
            verbosity: Verbosity::Verbose,
        );

        Assert::same(\substr_count($output, '[stdout]'), 2);
    }

    public function regularTestStartClearsAPreviouslyRecordedOverrideName(): void
    {
        $test = self::test('passingTest', Status::Passed);

        $output = self::capture(static function (TerminalLogger $logger) use ($test): void {
            $logger->testStartedFromInfo($test->info, 'Dataset #0 [x]');
            // A regular (non-dataset) start carries no override and must clear the recorded one.
            $logger->testStartedFromInfo($test->info);
            $logger->handleTestResult($test, 0);
        });

        Assert::string($output)->contains('passingTest')->notContains('Dataset #0 [x]');
    }

    public function batchStartInDotsModeEmitsNoBatchNode(): void
    {
        $test = self::test('describedTest', Status::Passed, attributes: ['description' => 'batch note']);

        $output = self::capture(
            static fn(TerminalLogger $logger) => $logger->batchStartedFromInfo($test->info),
            format: OutputFormat::Dots,
        );

        // The single-character Dots layout has no room for a batch node or its description.
        Assert::same($output, '');
    }

    public function multipleRunsAreListedUnderThePassingTest(): void
    {
        $runA = self::test('passingTest', Status::Passed);
        $runB = self::test('passingTest', Status::Failed);
        $test = self::test('passingTest', Status::Passed, attributes: [
            MultipleResult::class => new MultipleResult(['first' => $runA, 'second' => $runB]),
        ]);

        $output = self::capture(static fn(TerminalLogger $logger) => $logger->handleTestResult($test, 0));

        Assert::string($output)->contains('Run #1')->contains('Run #2');
    }

    public function multipleRunsAreOmittedInDotsMode(): void
    {
        $runA = self::test('passingTest', Status::Passed);
        $test = self::test('passingTest', Status::Passed, attributes: [
            MultipleResult::class => new MultipleResult(['only' => $runA]),
        ]);

        $output = self::capture(
            static fn(TerminalLogger $logger) => $logger->handleTestResult($test, 0),
            format: OutputFormat::Dots,
        );

        // Dots collapses each test to a single character; per-run rows would break that layout.
        Assert::same($output, '.');
    }

    public function printReportStatesTheNamePathAndFormat(): void
    {
        $path = new class implements \Stringable {
            #[\Override]
            public function __toString(): string
            {
                return 'build/report.xml';
            }
        };
        $report = new ReportInfo('junit', 'JUnit', $path);

        $output = self::capture(static fn(TerminalLogger $logger) => $logger->printReport($report));

        Assert::string($output)
            ->contains('JUnit:')
            ->contains('build/report.xml')
            ->contains('(junit)');
    }

    public function ensureHeaderPrintsTheRunHeader(): void
    {
        $output = self::capture(static fn(TerminalLogger $logger) => $logger->ensureHeader());

        Assert::string($output)->contains(Info::NAME);
    }

    public function printEnvironmentReportsTheHostFacts(): void
    {
        $output = self::capture(static fn(TerminalLogger $logger) => $logger->printEnvironment());

        Assert::string($output)
            ->contains('OS:')
            ->contains('PHP:')
            ->contains('XDebug:')
            ->contains('OPcache:');
    }

    public function emptyRunPrintsTheNoTestsBanner(): void
    {
        $run = self::run([], Status::Passed, summary: new Summary([]));

        $output = self::render($run, handled: []);

        Assert::string($output)->contains('NO TESTS');
    }

    public function failedTestWithoutAThrowableFallsBackToAGenericMessage(): void
    {
        $failed = self::test('failingTest', Status::Failed);
        $run = self::run([$failed], Status::Failed, summary: new Summary(['Failed' => 1]));

        $output = self::render($run, handled: [$failed]);

        Assert::string($output)->contains('Failures:')->contains('Test failed');
    }

    public function comparisonFailureRendersADiffBlockInTheFailureDetail(): void
    {
        $failure = new ComparisonFailure(
            expected: 'foo',
            actual: 'bar',
            value: 'value',
            assertion: 'is the same',
            context: '',
            reason: 'values differ',
        );
        $failed = self::test('failingTest', Status::Failed, failure: $failure);
        $run = self::run([$failed], Status::Failed, summary: new Summary(['Failed' => 1]));

        $output = self::render($run, handled: [$failed]);

        Assert::string($output)
            ->contains('--- Expected')
            ->contains('+++ Actual')
            ->contains('- foo')
            ->contains('+ bar');
    }

    public function failureOfAFilelessTestDefinitionStillRenders(): void
    {
        // An internal function reflection reports no file and no line, so the failure detail carries no
        // location header — the block must still render the name and message.
        $info = new TestInfo(
            name: 'internalFn',
            caseInfo: new CaseInfo(
                suiteIdentity: new SuiteIdentity('Output/Unit'),
                definition: new CaseDefinition(
                    name: SampleTestClass::class,
                    type: 'test',
                    file: Path::create(__FILE__),
                    reflection: new \ReflectionClass(SampleTestClass::class),
                ),
            ),
            testDefinition: new TestDefinition(new \ReflectionFunction('strlen')),
        );
        $failed = self::test(
            'failingTest',
            Status::Failed,
            failure: new \RuntimeException('no file here'),
            info: $info,
        );
        $run = self::run([$failed], Status::Failed, summary: new Summary(['Failed' => 1]));

        $output = self::render($run, handled: [$failed]);

        Assert::string($output)->contains('internalFn')->contains('no file here');
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
    private static function capture(
        \Closure $scenario,
        OutputFormat $format = OutputFormat::Compact,
        Verbosity $verbosity = Verbosity::Normal,
    ): string {
        $stream = \fopen('php://memory', 'rb+');
        \assert($stream !== false);

        try {
            $scenario(new TerminalLogger($format, $verbosity, $stream));
            \rewind($stream);
            $output = \stream_get_contents($stream);
        } finally {
            \fclose($stream);
        }

        return $output === false ? '' : $output;
    }

    /**
     * Renders with colorization forced off so exact-structure assertions read plain text. The flag is
     * process-global in {@see Style}, so its previous value is restored to avoid leaking into whatever
     * case runs next.
     *
     * @param \Closure(): string $render
     */
    private static function withoutColors(\Closure $render): string
    {
        $colors = Style::areColorsEnabled();
        Style::setColorsEnabled(false);

        try {
            return $render();
        } finally {
            Style::setColorsEnabled($colors);
        }
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
    private static function run(
        array $results,
        Status $status,
        ?Summary $summary = null,
        RunTiming $timing = new RunTiming(),
    ): RunResult {
        $summary ??= new Summary(['Passed' => 1]);
        $case = new CaseResult($results, $status, $summary);
        $suite = new SuiteResult([$case], $status, $summary);

        return new RunResult([$suite], $status, $summary, $timing);
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
