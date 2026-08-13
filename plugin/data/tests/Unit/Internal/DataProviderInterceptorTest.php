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
use Testo\Test;
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

    private static function createTestInfo(): TestInfo
    {
        $reflection = new \ReflectionMethod(MultiProviderTarget::class, 'target');
        $caseDefinition = new CaseDefinition(name: 'TestCase', type: 'test', file: Path::create(__FILE__));
        $caseInfo = new CaseInfo(suiteIdentity: new SuiteIdentity('Data/Unit'), definition: $caseDefinition);
        $testDefinition = new TestDefinition(reflection: $reflection);

        return new TestInfo(
            name: 'target',
            caseInfo: $caseInfo,
            testDefinition: $testDefinition,
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
