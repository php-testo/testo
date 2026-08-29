<?php

declare(strict_types=1);

namespace Tests\Fiber\Feature;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Value\Status;
use Testo\Fiber\Coroutine;
use Testo\Fiber\Exception\CompositeException;
use Testo\Fiber\Internal\CoroutineScopeInterceptor;
use Testo\Fiber\Internal\RunInFiberInterceptor;
use Testo\Fiber\RunInFiber;
use Testo\Filter\Group;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Tests\Fiber\Stub\FiberScenarios;

/**
 * How the fiber plugin maps onto Testo statuses. Each case runs a stub scenario through
 * {@see TestRunner} and asserts the resulting {@see Status}.
 */
#[Test]
#[Covers(RunInFiber::class)]
#[Covers(RunInFiberInterceptor::class)]
#[Covers(CoroutineScopeInterceptor::class)]
#[Covers(Coroutine::class)]
#[TestingSuite(path: __DIR__ . '/../Stub')]
final class StatusTest
{
    #[Group('async')]
    public function fiberTestPasses(): void
    {
        $result = TestRunner::runTest([FiberScenarios::class, 'runsInAFiber']);

        Assert::same($result->status, Status::Passed);
    }

    #[Group('async')]
    public function failureInsideFiberPropagates(): void
    {
        $result = TestRunner::runTest([FiberScenarios::class, 'failureInsideFiberPropagates']);

        Assert::same($result->status, Status::Failed);
    }

    public function untaggedTestPasses(): void
    {
        $result = TestRunner::runTest([FiberScenarios::class, 'untaggedRunsOnMainFiber']);

        Assert::same($result->status, Status::Passed);
    }

    #[Group('async')]
    public function unawaitedCoroutineFailureErrorsTheTest(): void
    {
        $result = TestRunner::runTest([FiberScenarios::class, 'unawaitedCoroutineFailure']);

        Assert::same($result->status, Status::Error);
        Assert::instanceOf($result->failure, CompositeException::class);
        Assert::instanceOf($result->failure->getPrevious(), \RuntimeException::class);
    }

    #[Group('async')]
    public function awaitedCoroutineFailureHandledInTestPasses(): void
    {
        $result = TestRunner::runTest([FiberScenarios::class, 'awaitedCoroutineFailureHandledInTest']);

        # await() marked the failure observed; the scope does not resurface it.
        Assert::same($result->status, Status::Passed);
    }

    #[Group('async')]
    public function bodyThrowKeepsWorkingWithExpectException(): void
    {
        $result = TestRunner::runTest([FiberScenarios::class, 'bodyThrowStaysUnwrapped']);

        # The body's own throw is not wrapped — #[ExpectException] matches it as usual.
        Assert::same($result->status, Status::Passed);
    }

    #[Group('async')]
    public function coroutineAssertionsCountTowardTheirTest(): void
    {
        $result = TestRunner::runTest([FiberScenarios::class, 'assertionsInsideCoroutinesCountForTheTest']);

        Assert::same($result->status, Status::Passed);
        # 1 assert in the body + 2 inside the coroutine, attributed to the same test.
        Assert::same($result->summary->metric('assertions'), 3);
    }

    #[Group('async')]
    public function failingBodyCancelsPendingCoroutines(): void
    {
        FiberScenarios::$cancellationLog = [];

        $result = TestRunner::runTest([FiberScenarios::class, 'failingBodyLeavesAPendingCoroutine']);

        Assert::same($result->status, Status::Failed);
        # The pending coroutine was cancelled at its suspension point, not driven to completion.
        Assert::same(FiberScenarios::$cancellationLog, ['cancelled']);
    }

    public function spawnWithoutScopeErrorsWithAHint(): void
    {
        $result = TestRunner::runTest([FiberScenarios::class, 'spawnWithoutFiberScope']);

        Assert::same($result->status, Status::Error);
        Assert::instanceOf($result->failure, \LogicException::class);
        Assert::string($result->failure->getMessage())->contains('RunInFiber');
    }
}
