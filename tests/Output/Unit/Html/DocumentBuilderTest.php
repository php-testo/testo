<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Html;

use Internal\Path;
use Testo\Application\Config\RunConfiguration;
use Testo\Application\Internal\EventDispatcher;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\RunResult;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Log\Level;
use Testo\Core\Log\Message;
use Testo\Core\Log\MessageLog;
use Testo\Core\Value\RunTiming;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Data\MultipleResult;
use Testo\Event\Framework\SessionStarting;
use Testo\Event\Test\TestDataSetStarting;
use Testo\Event\Test\TestPipelineStarting;
use Testo\Event\Test\TestRetrying;
use Testo\Output\Html\Internal\DocumentBuilder;
use Testo\Output\Html\Internal\Json;
use Testo\Output\Html\Internal\Recorder;
use Testo\Test;
use Tests\Output\Stub\Html\SampleTestClass;

/**
 * The report document: what it says about a run, and the two properties a consumer relies on — every
 * status is present, and the same run always encodes to the same bytes.
 */
#[Test]
#[Covers(DocumentBuilder::class)]
#[Covers(Recorder::class)]
final class DocumentBuilderTest
{
    public function theDocumentOpensWithItsSchemaVersion(): void
    {
        $document = self::build();

        // A consumer reads the version before anything else, and refuses a major it does not know rather
        // than rendering half a page — so the field cannot be somewhere in the middle.
        Assert::same(\array_key_first($document), 'schemaVersion');
        Assert::same($document['schemaVersion'], 1);
        Assert::same(\array_keys($document), [
            'schemaVersion', 'generator', 'run', 'environment', 'channels', 'levels', 'suites',
        ]);
    }

    public function everyStatusIsCountedIncludingTheOnesThatCountedNothing(): void
    {
        $document = self::build();

        // Insertion order would be whatever order tests finished in, which concurrency makes unstable;
        // the enum order is the same in every report. Zeros stay so a filter knows the status exists.
        Assert::same(\array_keys($document['run']['summary']['counts']), [
            'passed', 'failed', 'skipped', 'error', 'risky', 'flaky', 'cancelled', 'aborted',
        ]);
        Assert::same($document['run']['summary']['counts']['passed'], 2);
        Assert::same($document['run']['summary']['counts']['failed'], 1);
        Assert::same($document['run']['summary']['counts']['risky'], 0);
    }

    public function theRunSplitsExecutionFromPipelineOverheadAndStatesTheBoost(): void
    {
        $run = self::build()['run'];

        // Execution is the wall the bodies occupied (overlaps once); overhead is the tests phase minus it.
        // Both are non-negative, and declared work over execution — the boost — is at least 1.
        Assert::true($run['execution'] >= 0.0, "execution = {$run['execution']}");
        Assert::true($run['overhead'] >= 0.0, "overhead = {$run['overhead']}");
        Assert::true(
            $run['boost'] === null || $run['boost'] >= 1.0 - 1e-9,
            'boost = ' . \var_export($run['boost'], true),
        );
    }

    public function aRunEncodesToTheSameBytesEveryTime(): void
    {
        $recorder = new Recorder();
        $result = self::runResult(self::dispatcher($recorder));

        $first = self::builder($recorder)->build($result);
        $second = self::builder($recorder)->build($result);

        // Reports have to be diffable and testable with golden files, which rules out anything stamped
        // from the clock while building and any map left in insertion order.
        Assert::same(Json::artifact($first), Json::artifact($second));
    }

    public function aTestNamesTheFilterThatSelectsItAgain(): void
    {
        $test = self::firstTest(self::build());

        Assert::same($test['filter'], SampleTestClass::class . '::passingTest');
        Assert::same($test['id'], 'Output/Unit::' . SampleTestClass::class . '::passingTest');
    }

    public function groupsAreCollectedFromTheClassAndTheMethodAndSorted(): void
    {
        $document = self::build();
        $failing = $document['suites'][0]['cases'][0]['tests'][1];

        // The union of what the method and the class declare — the same set `--group` selects on — and in
        // a stated order, because the traversal's own order says nothing.
        Assert::same($failing['groups'], ['failure', 'reporting']);
    }

    public function messageTimesAreOffsetsFromTheRunStart(): void
    {
        $test = self::firstTest(self::build());

        // Absolute timestamps would make two runs of the same code produce different documents, and a
        // reader compares messages against each other rather than against the wall clock.
        Assert::same(\count($test['messages']), 2);
        Assert::true($test['messages'][0]['time'] < 1.0, (string) $test['messages'][0]['time']);
        Assert::same($test['messages'][0]['channel'], 'stdout');
        Assert::same($test['messages'][1]['level'], 'error');
    }

    public function channelOutputIsCappedAndTheCutIsStated(): void
    {
        $document = self::build(messageLimit: 8);
        $test = self::firstTest($document);

        // Output is the one part of a run with no natural size, so it is capped — and a report that cut
        // something must say so, or it reads as a test that printed less than it did.
        Assert::same($test['truncated']['messages']['total'], 2);
        Assert::same($test['truncated']['messages']['limit'], 8);
        Assert::true($test['truncated']['messages']['bytes'] > 8);
    }

    public function dataSetsCarryTheirCoordinatesLabelAndArguments(): void
    {
        $document = self::build();
        $sets = $document['suites'][0]['cases'][0]['tests'][2]['dataSets'];

        Assert::same(\count($sets), 1);
        Assert::same($sets[0]['providerIndex'], 0);
        Assert::same($sets[0]['index'], 1);
        // Addressed by index, labelled by key: provider keys are free to repeat.
        Assert::same($sets[0]['key'], 'a string');
        Assert::same($sets[0]['arguments'], [
            ['name' => 'input', 'type' => 'string', 'value' => "'x'"],
            ['name' => 'expected', 'type' => 'int', 'value' => '42'],
        ]);
    }

    public function attemptsAreListedOldestFirstWithTheKeptOneLast(): void
    {
        $document = self::build();
        $attempts = $document['suites'][0]['cases'][0]['tests'][3]['attempts'];

        // The retry interceptor returns the last attempt and drops the rest; without the discarded ones a
        // flaky test looks like it simply passed.
        Assert::same(\count($attempts), 2);
        Assert::same($attempts[0]['number'], 1);
        Assert::false($attempts[0]['kept']);
        Assert::same($attempts[0]['status'], 'failed');
        Assert::true(isset($attempts[0]['failure']));
        Assert::true($attempts[1]['kept']);
        Assert::same($attempts[1]['status'], 'flaky');
    }

    public function aFailureCarriesItsLocationSourceLineAndTrace(): void
    {
        $failure = self::build()['suites'][0]['cases'][0]['tests'][1]['failure'];

        Assert::same($failure['class'], \RuntimeException::class);
        Assert::same($failure['message'], 'boom');
        Assert::string($failure['file'])->contains('DocumentBuilderTest.php');
        // The line the failure points at, read back from the file — the one thing a stack frame cannot say.
        Assert::string($failure['sourceLine'])->contains('boom');
        Assert::true($failure['trace'] !== []);
    }

    public function theEnvironmentStatesTheInputButNeverTheEnvironmentVariables(): void
    {
        $document = self::build();
        $config = $document['environment']['config'];

        Assert::same($config['options'], ['group' => ['db']]);
        Assert::same($config['suites'], ['Output/Unit', 'Output/Feature']);
        // A report gets committed and shared; the process environment must not travel with it.
        Assert::string(Json::artifact($document))->notContains('do-not-report-me');
    }

    public function channelsAreListedWithCountsAndNoPresentation(): void
    {
        $channels = self::build()['channels'];

        // Names and counts only: how a channel looks is the renderer's business, and a document that
        // pinned colours would freeze them into every report ever written.
        Assert::same($channels, [
            ['name' => 'stderr', 'messages' => 1],
            ['name' => 'stdout', 'messages' => 1],
        ]);
    }

    /**
     * @param int<0, max> $messageLimit
     * @return array<non-empty-string, mixed>
     */
    private static function build(int $messageLimit = 65536): array
    {
        $recorder = new Recorder();
        $result = self::runResult(self::dispatcher($recorder));

        return self::builder($recorder, $messageLimit)->build($result);
    }

    /**
     * @param int<0, max> $messageLimit
     */
    private static function builder(Recorder $recorder, int $messageLimit = 65536): DocumentBuilder
    {
        return new DocumentBuilder(
            recorder: $recorder,
            config: new RunConfiguration(
                configFile: Path::create('testo.php'),
                options: ['group' => ['db'], 'json' => false],
                arguments: [],
            ),
            messageLimit: $messageLimit,
            suiteNames: ['Output/Unit', 'Output/Feature'],
        );
    }

    private static function dispatcher(Recorder $recorder): EventDispatcher
    {
        $dispatcher = new EventDispatcher();
        $recorder->configure($dispatcher);
        $dispatcher->dispatch(new SessionStarting());

        return $dispatcher;
    }

    /**
     * One suite with one case holding four tests: a passing one with output, a failing one, a data
     * provider, and a flaky one that needed two attempts.
     */
    private static function runResult(EventDispatcher $dispatcher): RunResult
    {
        $passing = self::start($dispatcher, 'passingTest');
        $failing = self::start($dispatcher, 'failingTest');
        $dataset = self::start($dispatcher, 'datasetTest');
        $flaky = self::start($dispatcher, 'flakyTest');

        $tests = [
            new TestResult(
                info: $passing,
                status: Status::Passed,
                messages: new MessageLog([
                    new Message(\microtime(true), 'stdout', Level::Info, "hello\n"),
                    new Message(\microtime(true), 'stderr', Level::Error, "a warning\n"),
                ]),
                summary: Summary::forTest(Status::Passed, 0.01)->withAddedMetric('assertions', 3),
            ),
            new TestResult(
                info: $failing,
                status: Status::Failed,
                failure: new \RuntimeException('boom'),
                summary: Summary::forTest(Status::Failed, 0.02),
            ),
            self::dataProviderResult($dispatcher, $dataset),
            self::flakyResult($dispatcher, $flaky),
        ];

        $case = new CaseResult(
            $tests,
            status: Status::Failed,
            summary: Summary::combine(\array_map(
                static fn(TestResult $r): Summary => $r->summary,
                $tests,
            )),
        );
        $suite = new SuiteResult([$case], status: Status::Failed, summary: $case->summary);

        return new RunResult(
            [$suite],
            status: Status::Failed,
            summary: $suite->summary,
            timing: new RunTiming(startup: 0.1, discovery: 0.2, tests: 0.15, teardown: 0.05),
        );
    }

    private static function dataProviderResult(EventDispatcher $dispatcher, TestInfo $info): TestResult
    {
        $set = $info->with(
            arguments: ['x', 42],
            identity: $info->identity->toDataSet(dataProvider: 0, dataSet: 1),
        );

        # A data set's label lives on the event and nowhere else: the address carries indexes, on purpose,
        # because provider keys are free to repeat.
        $dispatcher->dispatch(new TestDataSetStarting($set, 'a string', 0, 1));

        $setResult = new TestResult(
            info: $set,
            status: Status::Passed,
            summary: Summary::forTest(Status::Passed, 0.005)->withAddedMetric('assertions', 1),
        );
        $multiple = new MultipleResult([$setResult]);

        return new TestResult(
            info: $info,
            status: Status::Passed,
            result: $multiple,
            attributes: [MultipleResult::class => $multiple],
            summary: $setResult->summary,
        );
    }

    private static function flakyResult(EventDispatcher $dispatcher, TestInfo $info): TestResult
    {
        $dispatcher->dispatch(new TestRetrying(
            $info,
            2,
            new TestResult(
                info: $info,
                status: Status::Failed,
                failure: new \RuntimeException('flaky boom'),
                summary: Summary::forTest(Status::Failed, 0.03),
            ),
        ));

        return new TestResult(
            info: $info,
            status: Status::Flaky,
            summary: Summary::forTest(Status::Flaky, 0.04),
        );
    }

    /**
     * @param non-empty-string $method
     */
    private static function start(EventDispatcher $dispatcher, string $method): TestInfo
    {
        $info = self::makeTestInfo($method);
        # The report reads a test's start from the pipeline event: a finished result knows how long it took
        # but not when it began, and without that there is no timeline.
        $dispatcher->dispatch(new TestPipelineStarting($info));

        return $info;
    }

    /**
     * @param non-empty-string $method
     */
    private static function makeTestInfo(string $method): TestInfo
    {
        return new TestInfo(
            name: $method,
            caseInfo: new CaseInfo(
                suiteIdentity: new SuiteIdentity('Output/Unit'),
                definition: new CaseDefinition(
                    name: SampleTestClass::class,
                    type: 'test',
                    file: Path::create((string) (new \ReflectionClass(SampleTestClass::class))->getFileName()),
                    reflection: new \ReflectionClass(SampleTestClass::class),
                ),
            ),
            testDefinition: new TestDefinition(new \ReflectionMethod(SampleTestClass::class, $method)),
        );
    }

    /**
     * @param array<non-empty-string, mixed> $document
     * @return array<non-empty-string, mixed>
     */
    private static function firstTest(array $document): array
    {
        return $document['suites'][0]['cases'][0]['tests'][0];
    }
}
