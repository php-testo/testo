<?php

declare(strict_types=1);

namespace Tests\Bench\Unit;

use Internal\Path;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Assert;
use Testo\Bench;
use Testo\Bench\Internal\Pipeline\BenchInterceptor;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Value\Status;
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

    private static function bench(): Bench
    {
        return new Bench([static fn(): int => 1]);
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
