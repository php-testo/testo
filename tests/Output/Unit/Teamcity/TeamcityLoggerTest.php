<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Teamcity;

use Internal\Path;
use Testo\Assert;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Bench\Dto\BenchResult;
use Testo\Bench\Dto\CaseSet;
use Testo\Bench\Dto\Line;
use Testo\Bench\Dto\Snap;
use Testo\Bench\Dto\ValueRel;
use Testo\Assert\State\Assertion\ComparisonFailure;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\SuiteInfo;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\CaseDefinitions;
use Testo\Core\Definition\TestDefinitions;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Exception\CancelTest;
use Testo\Core\Exception\SkipTest;
use Testo\Core\Log\Level;
use Testo\Core\Log\Message;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Core\Report\ReportInfo;
use Testo\Output\Teamcity\Teamcity\TeamcityLogger;
use Testo\Test;
use Tests\Output\Stub\Teamcity\ConcreteSampleTestCase;
use Tests\Output\Stub\Teamcity\SampleTestClass;

#[Test]
#[Covers(TeamcityLogger::class)]
final class TeamcityLoggerTest
{
    public function handleSingleTestResultEmitsComparisonFailureAttributesForComparisonFailure(): void
    {
        $result = self::makeFailedResult(self::makeComparisonFailure());

        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->handleSingleTestResult($result));

        Assert::string($output)->contains("type='comparisonFailure'");
        Assert::string($output)->contains("expected='Array");
        Assert::string($output)->contains("actual='Array");
    }

    public function testFailedFromResultEmitsComparisonFailureAttributesForComparisonFailure(): void
    {
        $result = self::makeFailedResult(self::makeComparisonFailure());

        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->testFailedFromResult($result));

        Assert::string($output)->contains("type='comparisonFailure'");
        Assert::string($output)->contains("expected='Array");
        Assert::string($output)->contains("actual='Array");
    }

    public function handleSingleTestResultOmitsComparisonAttributesForGenericFailure(): void
    {
        $failure = new AssertionException(
            value: '"foo"',
            assertion: 'is blank',
            context: '',
            reason: 'value contains data',
            details: '',
        );
        $result = self::makeFailedResult($failure);

        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->handleSingleTestResult($result));

        Assert::string($output)->notContains("type='comparisonFailure'");
        Assert::string($output)->notContains("expected='");
        Assert::string($output)->notContains("actual='");
    }

    public function handleSingleTestResultPublishesBenchMetricsAsMetadataBeforeTheTestCloses(): void
    {
        $result = self::makeResult(Status::Passed)->withResult(self::benchResult());

        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->handleSingleTestResult($result));

        Assert::string($output)->contains('##teamcity[testMetadata');
        Assert::string($output)->contains("name='bench.iterations'");
        Assert::string($output)->contains("name='bench.shift.meanUs'");

        // testFinished closes the node the metadata attaches to, so every metadata line has to precede it.
        Assert::true(\strpos($output, 'testMetadata') < \strpos($output, 'testFinished'));
    }

    public function handleSingleTestResultPublishesNoMetadataForAnOrdinaryTest(): void
    {
        $output = self::capture(
            static fn(TeamcityLogger $logger) => $logger->handleSingleTestResult(self::makeResult(Status::Passed)),
        );

        Assert::string($output)->notContains('testMetadata');
    }

    public function handleSingleTestResultAttachesDataSetBenchMetricsToTheOverriddenName(): void
    {
        $result = self::makeResult(Status::Passed)->withResult(self::benchResult());

        $output = self::capture(
            static fn(TeamcityLogger $logger) => $logger->handleSingleTestResult($result, null, 'shiftVsPush#0:1'),
        );

        // A data-set run reports under the set's name, so its metadata has to carry that name too, not the
        // bare method's.
        Assert::string($output)->contains('##teamcity[testMetadata');
        Assert::string($output)->contains("testName='shiftVsPush#0:1'");
    }

    public function handleFailedTestRendersPreviousThrowableMessageInDetails(): void
    {
        $failure = new \RuntimeException(
            'outer failed',
            previous: new \LogicException('root cause exploded'),
        );
        $result = self::makeFailedResult($failure);

        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->handleSingleTestResult($result));

        Assert::string($output)->contains('Caused by:');
        Assert::string($output)->contains('LogicException: root cause exploded');
    }

    public function testStartedFromInfoAttributesInheritedTestToConcreteCaseClass(): void
    {
        $caseReflection = new \ReflectionClass(ConcreteSampleTestCase::class);
        $info = new TestInfo(
            name: 'inheritedTest',
            caseInfo: new CaseInfo(
                suiteIdentity: new SuiteIdentity('Output/Unit'),
                definition: new CaseDefinition(
                    name: ConcreteSampleTestCase::class,
                    type: 'test',
                    file: Path::create(__FILE__),
                    reflection: $caseReflection,
                ),
            ),
            // Method reflected through the subclass still reports the abstract base as its
            // declaring class — exactly what discovery stores for an inherited #[Test].
            testDefinition: new TestDefinition($caseReflection->getMethod('inheritedTest')),
        );

        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->testStartedFromInfo($info));

        Assert::string($output)->contains('ConcreteSampleTestCase::inheritedTest');
        Assert::string($output)->notContains('AbstractSampleTestCase::inheritedTest');
    }

    public function testStartedFromInfoAddressesADataSetByItsCoordinates(): void
    {
        $batch = self::makeInfo('passingTest');
        $first = $batch->with(identity: $batch->identity->toDataSet(dataProvider: 0, dataSet: 1));
        $second = $batch->with(identity: $batch->identity->toDataSet(dataProvider: 0, dataSet: 2));

        $a = self::capture(static fn(TeamcityLogger $logger) => $logger->testStartedFromInfo($first));
        $b = self::capture(static fn(TeamcityLogger $logger) => $logger->testStartedFromInfo($second));

        // The coordinates `--filter` takes, in the order it takes them. A key-based hint would collide
        // whenever a provider repeats one — these two would come out identical and select nothing when
        // pasted back.
        Assert::string($a)->contains('SampleTestClass::passingTest:0:1');
        Assert::string($b)->contains('SampleTestClass::passingTest:0:2');
    }

    public function testStartedFromInfoLeavesABatchNodeWithoutCoordinates(): void
    {
        $info = self::makeInfo('passingTest');

        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->testStartedFromInfo($info));

        // A test that is not a data set carries no tail at all, so the hint stays clickable as a method.
        Assert::string($output)->contains("SampleTestClass::passingTest'");
    }

    public function testStartedFromInfoEmitsDescriptionFromPhpDoc(): void
    {
        $info = self::makeInfo('describedTest');

        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->testStartedFromInfo($info));

        Assert::string($output)->contains("metainfo='Verifies the widget renders correctly.'");
    }

    public function testStartedFromInfoOmitsMetainfoWhenNoPhpDoc(): void
    {
        $info = self::makeInfo('passingTest');

        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->testStartedFromInfo($info));

        Assert::string($output)->notContains('metainfo=');
    }

    public function aStartingSuiteAnnouncesHowManyTestsItHolds(): void
    {
        $info = new SuiteInfo(
            name: 'Output/Unit',
            testCases: CaseDefinitions::fromArray(
                self::makeCase('passingTest', 'failingTest'),
                self::makeCase('describedTest'),
            ),
        );

        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->suiteStartedFromInfo($info));

        // Counts across every case of the suite, and lands before the suite opens so an IDE can size
        // its progress bar before the first test reports.
        Assert::string($output)->contains("##teamcity[testCount count='3']");
        Assert::true(
            \strpos($output, 'testCount') < \strpos($output, 'testSuiteStarted'),
        );
    }

    public function anEmptySuiteAnnouncesNoCount(): void
    {
        $info = new SuiteInfo(name: 'Output/Unit', testCases: CaseDefinitions::fromArray());

        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->suiteStartedFromInfo($info));

        Assert::string($output)->notContains('testCount');
    }

    public function logEmptyRunEmitsBuildProblem(): void
    {
        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->logEmptyRun());

        Assert::string($output)->contains('##teamcity[buildProblem');
        Assert::string($output)->contains("description='No tests were executed'");
    }

    public function logReportAnnouncesTheEntryFileOfAReportBeingWritten(): void
    {
        $entry = Path::create('runtime/report/index.html');
        $report = new ReportInfo('html', 'Testo HTML report', $entry);

        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->logReport($report));

        // The card names the file once, as configured; the message carries both forms, because only one of
        // them means anything on the machine reading it.
        Assert::string($output)->contains('##teamcity[testoReport ');
        Assert::string($output)->contains("format='html'");
        Assert::string($output)->contains("path='" . $entry->absolute() . "'");
        Assert::string($output)->contains("relativePath='runtime/report/index.html'");
    }

    public function logReportOmitsTheRelativePathOfAReportOutsideTheWorkingDirectory(): void
    {
        $report = new ReportInfo('html', 'Testo HTML report', Path::create('/tmp/report/index.html'));

        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->logReport($report));

        // Absent rather than empty: an empty value would read as the working directory itself.
        Assert::string($output)->contains("path='/tmp/report/index.html'");
        Assert::string($output)->notContains('relativePath=');
    }

    public function testStartedFromInfoStampsFlowIdFromIdentity(): void
    {
        $info = self::makeInfo('passingTest');

        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->testStartedFromInfo($info));

        Assert::string($output)->contains("flowId='{$info->identity->pipelineId}'");
    }

    public function aDataSetReportsInsideItsBatchesFlow(): void
    {
        $batch = self::makeInfo('passingTest');
        $dataSet = $batch->with(identity: $batch->identity->toDataSet(dataProvider: 0, dataSet: 1));

        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->testStartedFromInfo($dataSet));

        // The batch opened a nested suite in its own flow, and a data set reports inside that suite —
        // a flow of its own (its run differs from the batch's) would leave the suite behind.
        Assert::notSame($dataSet->identity->runtimeId, $batch->identity->runtimeId);
        Assert::string($output)->contains("flowId='{$batch->identity->pipelineId}'");
    }

    public function handleSingleTestResultStampsFlowIdFromIdentity(): void
    {
        $result = self::makeFailedResult(new \RuntimeException('boom'));

        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->handleSingleTestResult($result));

        // Both the failure and the finish message carry the test's flow, so a consumer keeps them together.
        Assert::same(\substr_count($output, "flowId='{$result->info->identity->pipelineId}'"), 2);
    }

    public function everyStatusReachesTheConsumerOnTheFinishMessage(): void
    {
        foreach (Status::cases() as $status) {
            $result = new TestResult(
                info: self::makeInfo('passingTest'),
                status: $status,
                failure: $status->isFailure() ? new \RuntimeException('boom') : null,
                attributes: ['duration' => 0],
            );

            $output = self::capture(static fn(TeamcityLogger $logger) => $logger->handleSingleTestResult($result));

            // The standard messages collapse eight outcomes into three shapes — a consumer that needs the
            // exact one reads it off `testFinished`, which every branch emits.
            $expected = \strtolower($status->name);
            Assert::string($output)->contains("##teamcity[testFinished");
            Assert::string($output)->contains("status='{$expected}'");
        }
    }

    public function aCancelledTestCarriesTheReasonFromTheException(): void
    {
        $result = self::makeResult(Status::Cancelled, new CancelTest('deadline exceeded while waiting for the queue'));

        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->handleSingleTestResult($result));

        // The reason lives in the thrown exception; a generic stand-in would hide why the run was aborted.
        Assert::string($output)->contains("message='deadline exceeded while waiting for the queue'");
    }

    public function aCancelledTestWithoutAReasonFallsBackToAGenericMessage(): void
    {
        $result = self::makeResult(Status::Cancelled, new CancelTest());

        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->handleSingleTestResult($result));

        Assert::string($output)->contains("message='Test cancelled'");
    }

    public function aSkippedTestCarriesTheReasonFromTheException(): void
    {
        $result = self::makeResult(Status::Skipped, new SkipTest('sqlite extension is missing'));

        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->handleSingleTestResult($result));

        Assert::string($output)->contains("message='sqlite extension is missing'");
    }

    public function aSkippedTestWithoutAReasonOmitsTheMessage(): void
    {
        $result = self::makeResult(Status::Skipped, new SkipTest());

        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->handleSingleTestResult($result));

        Assert::string($output)->contains('##teamcity[testIgnored');
        Assert::string($output)->notContains('message=');
    }

    public function aPassedTestReportsHowManyAssertionsItPerformed(): void
    {
        $result = new TestResult(
            info: self::makeInfo('passingTest'),
            status: Status::Passed,
            attributes: ['duration' => 0],
            summary: new Summary(metrics: ['assertions' => 7]),
        );

        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->handleSingleTestResult($result));

        Assert::string($output)->contains("assertions='7'");
    }

    public function aPassedTestNobodyCountedAssertionsForOmitsTheAttribute(): void
    {
        $result = new TestResult(
            info: self::makeInfo('passingTest'),
            status: Status::Passed,
            attributes: ['duration' => 0],
        );

        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->handleSingleTestResult($result));

        // The count comes from the Assert plugin; without it there is no number to report, and a
        // fabricated zero would read as an unasserted test.
        Assert::string($output)->notContains('assertions=');
    }

    public function logMessagePlacesTheOutputOnItsTestsNode(): void
    {
        $info = self::makeInfo('passingTest');
        $message = new Message(time: 0.0, channel: 'stdout', level: Level::Info, content: 'streamed line');

        $output = self::capture(
            static fn(TeamcityLogger $logger) => $logger->logMessage('someTest', $message, $info->identity),
        );

        // Streamed output has to name the node it came from, or a consumer attaches it to whichever test
        // happens to be open — which, when tests interleave, is somebody else's.
        Assert::string($output)->contains("nodeId='{$info->identity->runtimeId}'");
        Assert::string($output)->contains("flowId='{$info->identity->pipelineId}'");
        Assert::string($output)->contains('streamed line');
    }

    public function distinctTestsGetDistinctFlowIds(): void
    {
        $first = self::makeInfo('passingTest');
        $second = self::makeInfo('passingTest');

        // Two separate runs of the same method are distinct in-flight tests — their flows must differ so
        // that, when interleaved, a consumer never merges their messages.
        Assert::notSame($first->identity->pipelineId, $second->identity->pipelineId);

        $a = self::capture(static fn(TeamcityLogger $logger) => $logger->testStartedFromInfo($first));
        $b = self::capture(static fn(TeamcityLogger $logger) => $logger->testStartedFromInfo($second));

        Assert::string($a)->contains("flowId='{$first->identity->pipelineId}'");
        Assert::string($b)->contains("flowId='{$second->identity->pipelineId}'");
    }

    /**
     * Runs the callback against a logger writing to an in-memory stream and returns what it wrote.
     *
     * The logger writes straight to its stream (bypassing output buffering), so capture is done by
     * injecting a `php://memory` stream rather than `ob_start()`.
     *
     * @param \Closure(TeamcityLogger): void $callback
     */
    private static function capture(\Closure $callback): string
    {
        $stream = \fopen('php://memory', 'rb+');
        \assert($stream !== false);

        try {
            $callback(new TeamcityLogger($stream));
            \rewind($stream);
            $output = \stream_get_contents($stream);
        } finally {
            \fclose($stream);
        }

        return $output === false ? '' : $output;
    }

    private static function makeComparisonFailure(): ComparisonFailure
    {
        return new ComparisonFailure(
            expected: ['line1', 'line2', 'line3'],
            actual: ['line1', 'line2_changed', 'line3'],
            value: 'array(3)',
            assertion: 'is the same as `array(3)`',
            context: '',
            reason: 'expected `array(3)`, got `array(3)`',
        );
    }

    private static function makeFailedResult(\Throwable $failure): TestResult
    {
        return new TestResult(
            info: self::makeInfo('failingTest'),
            status: Status::Failed,
            failure: $failure,
            attributes: ['duration' => 0],
        );
    }

    private static function makeResult(Status $status, ?\Throwable $failure = null): TestResult
    {
        return new TestResult(
            info: self::makeInfo('passingTest'),
            status: $status,
            failure: $failure,
            attributes: ['duration' => 0],
        );
    }

    private static function benchResult(): BenchResult
    {
        return new BenchResult(
            cases: [new CaseSet('shift', [new Snap(calls: 20, memory: 0, time: 5.1)])],
            results: [],
            lines: [
                new Line(
                    place: 1,
                    name: 'shift',
                    avg: new ValueRel(5.1, 0.0),
                    med: new ValueRel(5.1, 0.0),
                    rstdev: 2.0,
                    favg: new ValueRel(5.1, 0.0),
                    frstdev: 2.0,
                    rejected: 0,
                    reports: [],
                ),
            ],
        );
    }

    /**
     * A case of {@see SampleTestClass} holding exactly the named methods as its tests.
     *
     * @param non-empty-string ...$methods
     */
    private static function makeCase(string ...$methods): CaseDefinition
    {
        $tests = new TestDefinitions();
        foreach ($methods as $method) {
            $tests->define(new \ReflectionMethod(SampleTestClass::class, $method));
        }

        return new CaseDefinition(
            name: SampleTestClass::class,
            type: 'test',
            file: Path::create(__FILE__),
            reflection: new \ReflectionClass(SampleTestClass::class),
            tests: $tests,
        );
    }

    /**
     * @param non-empty-string $method Method of {@see SampleTestClass} backing the test definition.
     */
    private static function makeInfo(string $method): TestInfo
    {
        return new TestInfo(
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
    }
}
