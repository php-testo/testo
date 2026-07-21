<?php

declare(strict_types=1);

namespace Tests\Fiber\Feature;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Value\Status;
use Testo\Fiber\Internal\RunInFiberInterceptor;
use Testo\Fiber\RunInFiber;
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
#[TestingSuite(path: __DIR__ . '/../Stub')]
final class StatusTest
{
    public function fiberTestPasses(): void
    {
        $result = TestRunner::runTest([FiberScenarios::class, 'runsInAFiber']);

        Assert::same($result->status, Status::Passed);
    }

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
}
