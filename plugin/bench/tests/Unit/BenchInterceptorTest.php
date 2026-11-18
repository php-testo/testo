<?php

declare(strict_types=1);

namespace Tests\Bench\Unit;

use Internal\Path;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Assert;
use Testo\Bench;
use Testo\Bench\Dto\BenchResult;
use Testo\Bench\Dto\CaseSet;
use Testo\Bench\Dto\Line;
use Testo\Bench\Dto\Snap;
use Testo\Bench\Dto\ValueRel;
use Testo\Bench\Internal\BenchMetadata;
use Testo\Bench\Internal\Pipeline\BenchInterceptor;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Metadata\Metadata;
use Testo\Core\Metadata\MetadataEntry;
use Testo\Core\Value\Status;
use Testo\Event\Test\TestDataSetFinished;
use Testo\Data\MultipleResult;
use Testo\Filter\DataPointer;
use Testo\Test;

#[Test]
#[Covers(BenchInterceptor::class)]
final class BenchInterceptorTest
{
    public function aggregatesEveryBenchmarkCase(): void
    {
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount): TestResult {
            ++$callCount;
            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = (new BenchInterceptor(self::createDispatcher()))->runTest(
            self::createTestInfo([self::bench(), self::bench()]),
            $next,
        );

        Assert::same($callCount, 2);
        Assert::same($result->status, Status::Passed);

        # Each benchmark case counts as a test; the aggregate folds their summaries.
        Assert::same($result->summary->total(), 2);
        Assert::same($result->summary->count(Status::Passed), 2);

        $multiple = $result->getAttribute(MultipleResult::class);
        Assert::instanceOf($multiple, MultipleResult::class);
        Assert::same(\count($multiple->results), 2);
    }

    public function noMatchingBenchmarkCasesYieldASingleRiskyTest(): void
    {
        $called = false;
        $next = static function (TestInfo $info) use (&$called): TestResult {
            $called = true;
            return new TestResult(info: $info, status: Status::Passed);
        };

        # A DataPointer that matches no benchmark case index leaves the result set empty.
        $result = (new BenchInterceptor(self::createDispatcher()))->runTest(
            self::createTestInfo(
                [self::bench(), self::bench()],
                new DataPointer(provider: 99, dataset: null),
            ),
            $next,
        );

        Assert::false($called);
        Assert::same($result->status, Status::Risky);
        Assert::instanceOf($result->result, \RuntimeException::class);
        Assert::null($result->getAttribute(MultipleResult::class));

        # Counts are left empty on purpose — the TestRunner stamps the final status late.
        Assert::same($result->summary->total(), 0);
    }

    public function theDataSetCoordinateSelectsTheOnlySetABenchmarkCaseHas(): void
    {
        $reached = [];
        $next = static function (TestInfo $info) use (&$reached): TestResult {
            $reached[] = [$info->identity->dataProvider, $info->identity->dataSet];
            return new TestResult(info: $info, status: Status::Passed);
        };

        $interceptor = new BenchInterceptor(self::createDispatcher());
        $benches = [self::bench(), self::bench(), self::bench()];

        # A benchmark case is a provider slot holding a single data set, so `:1:0` names the second one.
        $hit = $interceptor->runTest(
            self::createTestInfo($benches, new DataPointer(provider: 1, dataset: 0)),
            $next,
        );

        Assert::same($hit->status, Status::Passed);
        Assert::same($hit->summary->total(), 1);
        Assert::same($reached, [[1, 0]]);

        # There is no second data set inside that slot, so `:1:1` names nothing.
        $miss = $interceptor->runTest(
            self::createTestInfo($benches, new DataPointer(provider: 1, dataset: 1)),
            $next,
        );

        Assert::same($miss->status, Status::Risky);
        Assert::same($reached, [[1, 0]]);
    }

    public function everyDataSetResultCarriesItsBenchMetadataWhenAnnouncedFinished(): void
    {
        $finished = new \ArrayObject();
        $dispatcher = self::recordingDispatcher($finished);
        $preset = MetadataEntry::text('note', 'kept');
        $next = static fn(TestInfo $info): TestResult => (new TestResult(
            info: $info,
            status: Status::Passed,
            result: self::benchResult(),
        ))->withMetadata(new Metadata([$preset]));

        $result = (new BenchInterceptor($dispatcher))->runTest(
            self::createTestInfo([self::bench(), self::bench()]),
            $next,
        );

        Assert::count($finished, 2);
        $multiple = $result->getAttribute(MultipleResult::class);
        Assert::instanceOf($multiple, MultipleResult::class);
        foreach ([...$finished, ...$multiple->results] as $dataSet) {
            $names = \array_map(static fn(MetadataEntry $entry): string => $entry->name, $dataSet->metadata->entries);
            # Bench metadata is appended to what the test already reported, never replacing it.
            Assert::same($dataSet->metadata->entries[0], $preset);
            Assert::same($names[1], 'bench.current.Benchmark setup.iters');
            Assert::count($dataSet->metadata, 1 + \count(BenchMetadata::of(self::benchResult())));
        }
    }

    public function aSingleBenchmarkResultCarriesItsBenchMetadata(): void
    {
        $next = static fn(TestInfo $info): TestResult => new TestResult(
            info: $info,
            status: Status::Passed,
            result: self::benchResult(),
        );

        $result = (new BenchInterceptor(self::createDispatcher()))->runTest(
            self::createTestInfo([self::bench()]),
            $next,
        );

        Assert::count($result->metadata, \count(BenchMetadata::of(self::benchResult())));
        Assert::same($result->metadata->entries[0]->name, 'bench.current.Benchmark setup.iters');
    }

    public function aDataSetResultWithoutABenchResultGetsNoMetadata(): void
    {
        $finished = new \ArrayObject();
        $dispatcher = self::recordingDispatcher($finished);
        $next = static fn(TestInfo $info): TestResult => throw new \RuntimeException('boom');

        (new BenchInterceptor($dispatcher))->runTest(
            self::createTestInfo([self::bench(), self::bench()]),
            $next,
        );

        Assert::count($finished, 2);
        foreach ($finished as $dataSet) {
            Assert::same($dataSet->status, Status::Error);
            Assert::true($dataSet->metadata->isEmpty());
        }
    }

    private static function benchResult(): BenchResult
    {
        return new BenchResult(
            cases: [new CaseSet('current', [new Snap(calls: 10, memory: 0, time: 1.0)])],
            results: [],
            lines: [
                new Line(
                    place: 1,
                    name: 'current',
                    avg: new ValueRel(1.0, 0.0),
                    med: new ValueRel(1.0, 0.0),
                    rstdev: 0.0,
                    favg: new ValueRel(1.0, 0.0),
                    frstdev: 0.0,
                    rejected: 0,
                    reports: [],
                ),
            ],
        );
    }

    private static function bench(): Bench
    {
        return new Bench([static fn(): int => 1]);
    }

    /**
     * Collects the result of every {@see TestDataSetFinished} into `$finished`.
     *
     * @param \ArrayObject<int, TestResult> $finished
     */
    private static function recordingDispatcher(\ArrayObject $finished): EventDispatcherInterface
    {
        return new class ($finished) implements EventDispatcherInterface {
            public function __construct(private \ArrayObject $finished) {}

            #[\Override]
            public function dispatch(object $event): object
            {
                $event instanceof TestDataSetFinished and $this->finished->append($event->testResult);
                return $event;
            }
        };
    }

    private static function createDispatcher(): EventDispatcherInterface
    {
        return new class implements EventDispatcherInterface {
            #[\Override]
            public function dispatch(object $event): object
            {
                return $event;
            }
        };
    }

    /**
     * @param list<Bench> $benches
     */
    private static function createTestInfo(array $benches, ?DataPointer $dataPointer = null): TestInfo
    {
        $caseInfo = new CaseInfo(
            definition: new CaseDefinition(name: 'TestCase', type: 'bench', file: Path::create(__FILE__)),
            suiteIdentity: new SuiteIdentity('Bench/Unit'),
        );
        $testDefinition = new TestDefinition(reflection: new \ReflectionFunction(static fn() => null));

        $attributes = [Bench::class => $benches];
        $dataPointer === null or $attributes[DataPointer::class] = $dataPointer;

        return new TestInfo(
            name: 'target',
            caseInfo: $caseInfo,
            testDefinition: $testDefinition,
            attributes: $attributes,
        );
    }
}
