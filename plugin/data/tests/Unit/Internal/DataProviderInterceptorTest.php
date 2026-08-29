<?php

declare(strict_types=1);

namespace Tests\Data\Unit\Internal;

use Internal\Path;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Assert;
use Testo\Assert\ExpectException;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Value\CaseInstance;
use Testo\Core\Value\Status;
use Testo\Data\Internal\DataProviderInterceptor;
use Testo\Data\MultipleResult;
use Testo\Filter\DataPointer;
use Testo\Test;
use Tests\Data\Unit\Fixture\CombinatorTarget;
use Tests\Data\Unit\Fixture\InvalidProviderTarget;
use Tests\Data\Unit\Fixture\MultiProviderTarget;
use Tests\Data\Unit\Fixture\NonStaticProviderTarget;

/**
 * @see DataProviderInterceptor
 */
#[Test]
#[Covers(DataProviderInterceptor::class)]
#[Covers(MultipleResult::class)]
final class DataProviderInterceptorTest
{
    /**
     * All data sets from every provider attribute must be run and aggregated,
     * not just the ones from the last provider.
     */
    public function collectsResultsFromAllProviders(): void
    {
        $dispatcher = self::createDispatcher();
        $interceptor = new DataProviderInterceptor($dispatcher);
        $info = self::createTestInfo();
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount): TestResult {
            ++$callCount;
            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest($info, $next);

        // firstProvider(2) + secondProvider(3) + DataSet(1) = 6 data sets across 3 providers
        Assert::same($callCount, 6);
        Assert::same($result->status, Status::Passed);

        $summary = $result->getAttribute(MultipleResult::class);
        Assert::true($summary instanceof MultipleResult);
        Assert::same(\count($summary->results), 6);
    }

    public function supportsNonStaticProviderMethodBoundToInstance(): void
    {
        $target = new NonStaticProviderTarget();
        $instance = new class($target) implements CaseInstance {
            public function __construct(private readonly NonStaticProviderTarget $obj) {}

            #[\Override]
            public function getInstance(): object { return $this->obj; }

            #[\Override]
            public function hasInstance(): bool { return true; }
        };

        $dispatcher = self::createDispatcher();
        $interceptor = new DataProviderInterceptor($dispatcher);
        $info = self::createTestInfoWithInstance($instance);
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount): TestResult {
            ++$callCount;
            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest($info, $next);

        // instanceProvider() returns [[10], [20]] — 2 data sets
        Assert::same($callCount, 2);
        Assert::same($result->status, Status::Passed);
    }

    #[ExpectException(\LogicException::class)]
    public function throwsWhenNonStaticProviderUsedWithoutClassInstance(): void
    {
        $dispatcher = self::createDispatcher();
        $interceptor = new DataProviderInterceptor($dispatcher);

        // CaseInfo with no instance — the null coalescing throw should fire.
        $reflection = new \ReflectionMethod(NonStaticProviderTarget::class, 'target');
        $caseDefinition = new CaseDefinition(name: 'TestCase', type: 'test', file: Path::create(__FILE__));
        $caseInfo = new CaseInfo(suiteIdentity: new SuiteIdentity('Data/Unit'), definition: $caseDefinition, instance: null);
        $testDefinition = new TestDefinition(reflection: $reflection);
        $info = new TestInfo(name: 'target', caseInfo: $caseInfo, testDefinition: $testDefinition);

        $interceptor->runTest($info, static fn(TestInfo $i): TestResult => new TestResult(info: $i, status: Status::Passed));
    }

    /**
     * A DataPointer selects one provider by index; the others are skipped entirely.
     */
    public function dataPointerRunsOnlyTheSelectedProvider(): void
    {
        $interceptor = new DataProviderInterceptor(self::createDispatcher());
        // Provider #1 of MultiProviderTarget is secondProvider(): [[3], [4], [5]].
        $info = self::createTestInfo([DataPointer::class => new DataPointer(1, null)]);
        $seen = [];
        $next = static function (TestInfo $info) use (&$seen): TestResult {
            $seen[] = $info->arguments;
            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($seen, [[3], [4], [5]]);
        Assert::same($result->status, Status::Passed);
        $multiple = $result->getAttribute(MultipleResult::class);
        Assert::true($multiple instanceof MultipleResult);
        Assert::same(\count($multiple->results), 3);
    }

    /**
     * A DataPointer with a dataset index narrows the selected provider to a single data set.
     */
    public function dataPointerRunsOnlyTheSelectedDataSet(): void
    {
        $interceptor = new DataProviderInterceptor(self::createDispatcher());
        // Provider #1, data set #1 → the second entry of [[3], [4], [5]] == [4].
        $info = self::createTestInfo([DataPointer::class => new DataPointer(1, 1)]);
        $seen = [];
        $next = static function (TestInfo $info) use (&$seen): TestResult {
            $seen[] = $info->arguments;
            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($seen, [[4]]);
        $multiple = $result->getAttribute(MultipleResult::class);
        Assert::true($multiple instanceof MultipleResult);
        Assert::same(\count($multiple->results), 1);
    }

    /**
     * An exception thrown by the test body is caught per data set and turned into an
     * Error result, without aborting the sibling data sets or propagating out of the batch.
     */
    public function errorsFromTheTestBodyBecomePerDataSetErrorResults(): void
    {
        $interceptor = new DataProviderInterceptor(self::createDispatcher());
        $info = self::createTestInfo();
        $boom = new \RuntimeException('boom');
        $next = static function (TestInfo $info) use ($boom): TestResult {
            throw $boom;
        };

        $result = $interceptor->runTest($info, $next);

        // All 6 data sets still ran and each produced an Error result carrying the throwable.
        Assert::same($result->status, Status::Failed);
        $multiple = $result->getAttribute(MultipleResult::class);
        Assert::true($multiple instanceof MultipleResult);
        Assert::same(\count($multiple->results), 6);
        foreach ($multiple->results as $r) {
            Assert::same($r->status, Status::Error);
            Assert::same($r->failure, $boom);
        }
    }

    /**
     * DataZip pairs the providers position by position, yielding as many data sets as the
     * shorter provider and merging one argument from each into a single set.
     */
    public function zipsProvidersInParallel(): void
    {
        $interceptor = new DataProviderInterceptor(self::createDispatcher());
        $info = self::createInfoFor(CombinatorTarget::class, 'zipped');
        $seen = [];
        $next = static function (TestInfo $info) use (&$seen): TestResult {
            $seen[] = $info->arguments;
            return new TestResult(info: $info, status: Status::Passed);
        };

        $interceptor->runTest($info, $next);

        // letters [a=>[10], b=>[20]] zipped with numbers [x=>[1], y=>[2]].
        Assert::same($seen, [[10, 1], [20, 2]]);
    }

    /**
     * DataCross yields the cartesian product of the providers.
     */
    public function crossesProvidersCartesian(): void
    {
        $interceptor = new DataProviderInterceptor(self::createDispatcher());
        $info = self::createInfoFor(CombinatorTarget::class, 'crossed');
        $seen = [];
        $next = static function (TestInfo $info) use (&$seen): TestResult {
            $seen[] = $info->arguments;
            return new TestResult(info: $info, status: Status::Passed);
        };

        $interceptor->runTest($info, $next);

        Assert::same($seen, [[10, 1], [10, 2], [20, 1], [20, 2]]);
    }

    /**
     * A DataCross where one provider is empty yields no combinations, so the batch runs
     * nothing and is reported as Risky.
     */
    public function crossWithAnEmptyProviderExpandsToNothing(): void
    {
        $interceptor = new DataProviderInterceptor(self::createDispatcher());
        $info = self::createInfoFor(CombinatorTarget::class, 'crossedWithEmpty');
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount): TestResult {
            ++$callCount;
            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($callCount, 0);
        Assert::same($result->status, Status::Risky);
        Assert::null($result->getAttribute(MultipleResult::class));
    }

    /**
     * A DataCross with no providers yields no combinations either.
     */
    public function crossWithNoProvidersExpandsToNothing(): void
    {
        $interceptor = new DataProviderInterceptor(self::createDispatcher());
        $info = self::createInfoFor(CombinatorTarget::class, 'crossedNothing');
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount): TestResult {
            ++$callCount;
            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($callCount, 0);
        Assert::same($result->status, Status::Risky);
    }

    /**
     * DataUnion concatenates the providers into one sequence.
     */
    public function unionsProvidersSequentially(): void
    {
        $interceptor = new DataProviderInterceptor(self::createDispatcher());
        $info = self::createInfoFor(CombinatorTarget::class, 'unioned');
        $seen = [];
        $next = static function (TestInfo $info) use (&$seen): TestResult {
            $seen[] = $info->arguments;
            return new TestResult(info: $info, status: Status::Passed);
        };

        $interceptor->runTest($info, $next);

        Assert::same($seen, [[10], [20], [1], [2]]);
    }

    /**
     * A DataProvider naming a method that does not exist (and is not a callable) is rejected.
     */
    public function throwsWhenProviderMethodIsUnknown(): void
    {
        $interceptor = new DataProviderInterceptor(self::createDispatcher());
        $info = self::createInfoFor(InvalidProviderTarget::class, 'unknownMethod');
        $next = static fn(TestInfo $i): TestResult => new TestResult(info: $i, status: Status::Passed);

        $caught = null;
        try {
            $interceptor->runTest($info, $next);
        } catch (\InvalidArgumentException $e) {
            $caught = $e;
        }

        Assert::notNull($caught);
        Assert::same($caught->getMessage(), 'DataProvider provider must be a callable or method name string.');
    }

    /**
     * A DataProvider whose method returns a non-iterable is rejected.
     */
    public function throwsWhenProviderReturnsNonIterable(): void
    {
        $interceptor = new DataProviderInterceptor(self::createDispatcher());
        $info = self::createInfoFor(InvalidProviderTarget::class, 'scalarReturn');
        $next = static fn(TestInfo $i): TestResult => new TestResult(info: $i, status: Status::Passed);

        $caught = null;
        try {
            $interceptor->runTest($info, $next);
        } catch (\InvalidArgumentException $e) {
            $caught = $e;
        }

        Assert::notNull($caught);
        Assert::same($caught->getMessage(), 'Data provider must return an iterable of data sets.');
    }

    /**
     * A data set that is not an array of arguments is rejected while iterating the provider.
     */
    public function throwsWhenDataSetIsNotAnArray(): void
    {
        $interceptor = new DataProviderInterceptor(self::createDispatcher());
        $info = self::createInfoFor(InvalidProviderTarget::class, 'nonArrayDataSet');
        $next = static fn(TestInfo $i): TestResult => new TestResult(info: $i, status: Status::Passed);

        $caught = null;
        try {
            $interceptor->runTest($info, $next);
        } catch (\InvalidArgumentException $e) {
            $caught = $e;
        }

        Assert::notNull($caught);
        Assert::same($caught->getMessage(), 'Each data set must be an array of arguments.');
    }

    private static function createDispatcher(): EventDispatcherInterface
    {
        return new class() implements EventDispatcherInterface {
            /** @var list<object> */
            public array $dispatched = [];

            #[\Override]
            public function dispatch(object $event): object
            {
                $this->dispatched[] = $event;
                return $event;
            }
        };
    }

    /**
     * @param array<non-empty-string, mixed> $attributes
     */
    private static function createTestInfo(array $attributes = []): TestInfo
    {
        return self::createInfoFor(MultiProviderTarget::class, 'target', $attributes);
    }

    /**
     * @param class-string $class
     * @param non-empty-string $method
     * @param array<non-empty-string, mixed> $attributes
     */
    private static function createInfoFor(string $class, string $method, array $attributes = []): TestInfo
    {
        $reflection = new \ReflectionMethod($class, $method);
        $caseDefinition = new CaseDefinition(name: 'TestCase', type: 'test', file: Path::create(__FILE__));
        $caseInfo = new CaseInfo(suiteIdentity: new SuiteIdentity('Data/Unit'), definition: $caseDefinition);
        $testDefinition = new TestDefinition(reflection: $reflection);

        return new TestInfo(
            name: $method,
            caseInfo: $caseInfo,
            testDefinition: $testDefinition,
            attributes: $attributes,
        );
    }

    private static function createTestInfoWithInstance(CaseInstance $instance): TestInfo
    {
        $reflection = new \ReflectionMethod(NonStaticProviderTarget::class, 'target');
        $caseDefinition = new CaseDefinition(name: 'TestCase', type: 'test', file: Path::create(__FILE__));
        $caseInfo = new CaseInfo(suiteIdentity: new SuiteIdentity('Data/Unit'), definition: $caseDefinition, instance: $instance);
        $testDefinition = new TestDefinition(reflection: $reflection);

        return new TestInfo(
            name: 'target',
            caseInfo: $caseInfo,
            testDefinition: $testDefinition,
        );
    }
}
