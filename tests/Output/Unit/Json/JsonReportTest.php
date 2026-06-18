<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Json;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\RunResult;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Log\Level;
use Testo\Core\Log\Message;
use Testo\Core\Log\MessageLog;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
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
                definition: new CaseDefinition(name: null, type: 'test', reflection: null),
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
        $run = new RunResult([$suite], $status, $duration, $summary);

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
                definition: new CaseDefinition(
                    name: SampleTestClass::class,
                    type: 'test',
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
}
