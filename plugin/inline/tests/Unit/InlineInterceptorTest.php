<?php

declare(strict_types=1);

namespace Tests\Inline\Unit;

use Internal\Path;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Assert;
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
use Testo\Inline\Internal\InlineInterceptor;
use Testo\Inline\TestInline;
use Testo\Test;

#[Test]
#[Covers(InlineInterceptor::class)]
final class InlineInterceptorTest
{
    public function aggregatesEveryInlineCase(): void
    {
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount): TestResult {
            ++$callCount;
            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = (new InlineInterceptor(self::createDispatcher()))->runTest(
            self::createTestInfo([new TestInline([1]), new TestInline([2])]),
            $next,
        );

        Assert::same($callCount, 2);
        Assert::same($result->status, Status::Passed);

        # Each inline case counts as a test; the aggregate folds their summaries.
        Assert::same($result->summary->total(), 2);
        Assert::same($result->summary->count(Status::Passed), 2);

        $multiple = $result->getAttribute(MultipleResult::class);
        Assert::instanceOf($multiple, MultipleResult::class);
        Assert::same(\count($multiple->results), 2);
    }

    public function noMatchingInlineCasesYieldASingleRiskyTest(): void
    {
        $called = false;
        $next = static function (TestInfo $info) use (&$called): TestResult {
            $called = true;
            return new TestResult(info: $info, status: Status::Passed);
        };

        # A DataPointer that matches no inline case index leaves the result set empty.
        $result = (new InlineInterceptor(self::createDispatcher()))->runTest(
            self::createTestInfo(
                [new TestInline([1]), new TestInline([2])],
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
     * @param list<TestInline> $inlines
     */
    private static function createTestInfo(array $inlines, ?DataPointer $dataPointer = null): TestInfo
    {
        $caseInfo = new CaseInfo(
            definition: new CaseDefinition(name: 'TestCase', type: 'test', file: Path::create(__FILE__)),
            suiteIdentity: new SuiteIdentity('Inline/Unit')
        );
        $testDefinition = new TestDefinition(reflection: new \ReflectionFunction(static fn() => null));

        $attributes = [TestInline::class => $inlines];
        $dataPointer === null or $attributes[DataPointer::class] = $dataPointer;

        return new TestInfo(
            name: 'target',
            caseInfo: $caseInfo,
            testDefinition: $testDefinition,
            attributes: $attributes,
        );
    }
}
