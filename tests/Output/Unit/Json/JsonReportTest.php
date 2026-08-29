<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Json;

use Internal\Path;
use Testo\Assert;
use Testo\Bench\Dto\BenchResult;
use Testo\Bench\Dto\CaseSet;
use Testo\Bench\Dto\Line;
use Testo\Bench\Dto\Snap;
use Testo\Bench\Dto\ValueRel;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\RunResult;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Context\Identity\TestIdentity;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Log\Level;
use Testo\Core\Log\Message;
use Testo\Core\Log\MessageLog;
use Testo\Core\Value\RunTiming;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Data\MultipleResult;
use Testo\Output\Json\Internal\JsonReport;
use Testo\Test;
use Tests\Output\Stub\JUnit\SampleTestClass;

#[Test]
#[Covers(JsonReport::class)]
final class JsonReportTest
{
    public function passingRunReportsStatusTotalsAndEmptyFailures(): void
    {
        $report = self::decode(self::run(
            Status::Passed,
            duration: 1.25,
            summary: new Summary(['Passed' => 3], duration: 1.25),
            results: [self::test('passingTest', Status::Passed)],
        ));

        Assert::same($report['status'], 'passed');
        Assert::same($report['duration'], 1.25);
        Assert::same($report['totals'], ['total' => 3, 'passed' => 3]);
        Assert::same($report['failures'], []);
    }

    public function failedTestCarriesThrowableLocationAndTrace(): void
    {
        $failure = new \RuntimeException('boom');
        $report = self::decode(self::run(
            Status::Failed,
            results: [self::test('failingTest', Status::Failed, $failure)],
        ));

        Assert::count($report['failures'], 1);
        $failed = $report['failures'][0];

        Assert::same($failed['test'], SampleTestClass::class . '::failingTest');
        Assert::same($failed['status'], 'failed');
        Assert::same($failed['exception'], \RuntimeException::class);
        Assert::same($failed['message'], 'boom');
        Assert::same($failed['file'], $failure->getFile());
        Assert::same($failed['line'], $failure->getLine());
        Assert::true(\is_array($failed['trace']));
        Assert::false(\array_key_exists('causedBy', $failed));
        Assert::false(\array_key_exists('output', $failed));
    }

    public function onlyFailedAndErrorTestsAreListed(): void
    {
        $report = self::decode(self::run(
            Status::Failed,
            results: [
                self::test('passingTest', Status::Passed),
                self::test('failingTest', Status::Failed, new \RuntimeException('f')),
                self::test('passingTest', Status::Skipped),
                self::test('failingTest', Status::Error, new \LogicException('e')),
                self::test('passingTest', Status::Risky),
            ],
        ));

        $statuses = \array_map(static fn(array $f): string => $f['status'], $report['failures']);
        Assert::same($statuses, ['failed', 'error']);
    }

    public function previousChainIsReportedAsCausedBy(): void
    {
        $root = new \LogicException('root cause');
        $middle = new \DomainException('wrapping', previous: $root);
        $failure = new \RuntimeException('top', previous: $middle);

        $report = self::decode(self::run(
            Status::Failed,
            results: [self::test('failingTest', Status::Error, $failure)],
        ));
        $failed = $report['failures'][0];

        Assert::count($failed['causedBy'], 2);
        Assert::same($failed['causedBy'][0]['exception'], \DomainException::class);
        Assert::same($failed['causedBy'][0]['message'], 'wrapping');
        Assert::same($failed['causedBy'][1]['exception'], \LogicException::class);
        Assert::same($failed['causedBy'][1]['message'], 'root cause');
    }

    public function capturedOutputIsReported(): void
    {
        $messages = new MessageLog([
            new Message(0.0, 'stdout', Level::Info, 'hello from test'),
            new Message(0.0, 'sql-log', Level::Debug, 'SELECT 1'),
        ]);

        $report = self::decode(self::run(
            Status::Failed,
            results: [self::test('failingTest', Status::Failed, new \RuntimeException('x'), $messages)],
        ));

        Assert::same($report['failures'][0]['output'], [
            ['channel' => 'stdout', 'content' => 'hello from test'],
            ['channel' => 'sql-log', 'content' => 'SELECT 1'],
        ]);
    }

    public function consecutiveSameChannelMessagesAreCoalesced(): void
    {
        $messages = new MessageLog([
            new Message(0.0, 'stdout', Level::Info, "Foo 0\n"),
            new Message(0.0, 'stdout', Level::Info, "Foo 1\n"),
            new Message(0.0, 'sql-log', Level::Debug, 'SELECT 1'),
            new Message(0.0, 'stdout', Level::Info, "Bar\n"),
        ]);

        $report = self::decode(self::run(
            Status::Failed,
            results: [self::test('failingTest', Status::Failed, new \RuntimeException('x'), $messages)],
        ));

        Assert::same($report['failures'][0]['output'], [
            ['channel' => 'stdout', 'content' => "Foo 0\nFoo 1\n"],
            ['channel' => 'sql-log', 'content' => 'SELECT 1'],
            ['channel' => 'stdout', 'content' => "Bar\n"],
        ]);
    }

    public function malformedUtf8InMessagesIsSubstitutedNotThrown(): void
    {
        $messages = new MessageLog([new Message(0.0, 'stdout', Level::Info, "bad \x80\xFF byte")]);

        $report = self::decode(self::run(
            Status::Failed,
            results: [self::test('failingTest', Status::Failed, new \RuntimeException("msg \xFF"), $messages)],
        ));

        Assert::same($report['status'], 'failed');
        Assert::count($report['failures'], 1);
        Assert::same($report['failures'][0]['output'][0]['channel'], 'stdout');
    }

    public function freeFunctionTestUsesFunctionFqn(): void
    {
        $reflection = new \ReflectionFunction('strlen');
        $info = new TestInfo(
            name: 'free',
            caseInfo: new CaseInfo(
                suiteIdentity: new SuiteIdentity('Output/Unit'),
                definition: new CaseDefinition(name: null, type: 'test', file: Path::create(__FILE__), reflection: null),
            ),
            testDefinition: new TestDefinition($reflection),
        );
        $result = new TestResult(info: $info, status: Status::Failed, failure: new \RuntimeException('boom'));

        $report = self::decode(self::run(Status::Failed, results: [$result]));

        Assert::same($report['failures'][0]['test'], 'strlen');
    }

    public function totalsOmitZeroCountStatuses(): void
    {
        $report = self::decode(self::run(
            Status::Failed,
            summary: new Summary(['Passed' => 2, 'Failed' => 1]),
            results: [self::test('failingTest', Status::Failed, new \RuntimeException('x'))],
        ));

        Assert::same($report['totals'], ['total' => 3, 'passed' => 2, 'failed' => 1]);
    }

    public function emptyRunReportsRiskyStatusAndZeroTotal(): void
    {
        $run = new RunResult([], Status::Risky, new Summary());

        $report = self::decode((new JsonReport())->generate($run));

        Assert::same($report['status'], 'risky');
        Assert::same($report['totals'], ['total' => 0]);
        Assert::same($report['failures'], []);
    }

    public function benchmarkResultIsReportedAsData(): void
    {
        $report = self::decode(self::run(
            Status::Passed,
            results: [self::benchTest('shiftVsPush', self::benchResult())],
        ));

        Assert::count($report['benchmarks'], 1);
        $bench = $report['benchmarks'][0];

        Assert::same($bench['test'], SampleTestClass::class . '::shiftVsPush');
        Assert::same($bench['iterations'], 1);
        Assert::same($bench['cases'][0]['name'], 'shift');
        Assert::same($bench['cases'][0]['mean'], 5.1);
    }

    public function aRunWithoutBenchmarksOmitsTheSectionEntirely(): void
    {
        // Byte-for-byte identical to before the feature: the key is absent, not an empty array.
        $report = self::decode(self::run(
            Status::Passed,
            results: [self::test('passingTest', Status::Passed)],
        ));

        Assert::false(\array_key_exists('benchmarks', $report));
    }

    public function aRepeatableBenchmarkContributesOneEntryPerDataSet(): void
    {
        $identity = (new SuiteIdentity('Output/Unit'))
            ->toCase(SampleTestClass::class, 'bench', Path::create(__FILE__))
            ->toTest('shiftVsPush');

        $multiple = new MultipleResult([
            self::benchTest('shiftVsPush', self::benchResult(), $identity->toDataSet(0, 0)),
            self::benchTest('shiftVsPush', self::benchResult(), $identity->toDataSet(0, 1)),
        ]);
        // The umbrella result of a data-provider run carries no return value of its own; each set does.
        $test = self::test('passingTest', Status::Passed)
            ->withAttribute(MultipleResult::class, $multiple);

        $report = self::decode(self::run(Status::Passed, results: [$test]));

        // The single umbrella result is not itself an entry; each data set is, addressed by the same
        // coordinates `--filter=method:0:1` names.
        Assert::count($report['benchmarks'], 2);
        Assert::same($report['benchmarks'][0]['dataProvider'], 0);
        Assert::same($report['benchmarks'][0]['dataSet'], 0);
        Assert::same($report['benchmarks'][1]['dataSet'], 1);
    }

    /**
     * @return array<non-empty-string, mixed>
     */
    private static function decode(string $json): array
    {
        /** @var array<non-empty-string, mixed> */
        return \json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * Wraps the given test results in a single suite/case and renders the report.
     *
     * @param list<TestResult> $results
     */
    private static function run(
        Status $status,
        float $duration = 0.0,
        ?Summary $summary = null,
        array $results = [],
    ): string {
        $summary ??= new Summary();
        $case = new CaseResult($results, $status, $summary);
        $suite = new SuiteResult([$case], $status, $summary);
        $run = new RunResult([$suite], $status, $summary, new RunTiming(tests: $duration));

        return (new JsonReport())->generate($run);
    }

    private static function test(
        string $method,
        Status $status,
        ?\Throwable $failure = null,
        ?MessageLog $messages = null,
    ): TestResult {
        $info = new TestInfo(
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
            messages: $messages ?? new MessageLog(),
        );
    }

    private static function benchTest(
        string $method,
        BenchResult $result,
        ?TestIdentity $identity = null,
    ): TestResult {
        $info = new TestInfo(
            name: $method,
            caseInfo: new CaseInfo(
                suiteIdentity: new SuiteIdentity('Output/Unit'),
                definition: new CaseDefinition(
                    name: SampleTestClass::class,
                    type: 'bench',
                    file: Path::create(__FILE__),
                    reflection: new \ReflectionClass(SampleTestClass::class),
                ),
            ),
            testDefinition: new TestDefinition(new \ReflectionMethod(SampleTestClass::class, 'passingTest')),
            identity: $identity,
        );

        return new TestResult(info: $info, status: Status::Passed, result: $result);
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
}
