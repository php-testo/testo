<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Teamcity;

use Internal\Path;
use Testo\Assert;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Assert\State\Assertion\ComparisonFailure;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Log\Level;
use Testo\Core\Log\Message;
use Testo\Core\Value\Status;
use Testo\Output\Teamcity\Teamcity\TeamcityLogger;
use Testo\Test;
use Tests\Output\Stub\Teamcity\ConcreteSampleTestCase;
use Tests\Output\Stub\Teamcity\SampleTestClass;

#[Test]
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

    public function logEmptyRunEmitsBuildProblem(): void
    {
        $output = self::capture(static fn(TeamcityLogger $logger) => $logger->logEmptyRun());

        Assert::string($output)->contains('##teamcity[buildProblem');
        Assert::string($output)->contains("description='No tests were executed'");
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
