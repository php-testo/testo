<?php

declare(strict_types=1);

namespace Tests\Async\Feature;

use Testo\Assert;
use Testo\Async;
use Testo\Async\Internal\AsyncRunInterceptor;
use Testo\Codecov\Covers;
use Testo\Core\Value\Status;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Tests\Async\Stub\AsyncScenarios;

/**
 * How the async plugin maps onto Testo statuses. Each case runs a stub scenario through
 * {@see TestRunner} and asserts the resulting {@see Status}.
 */
#[Test]
#[Covers(Async::class)]
#[Covers(AsyncRunInterceptor::class)]
#[TestingSuite(path: __DIR__ . '/../Stub')]
final class AsyncStatusTest
{
    public function asyncTestPasses(): void
    {
        $result = TestRunner::runTest([AsyncScenarios::class, 'asyncRunsOnLoop']);

        Assert::same($result->status, Status::Passed);
    }

    public function failureInsideCoroutinePropagates(): void
    {
        $result = TestRunner::runTest([AsyncScenarios::class, 'failureInsideCoroutinePropagates']);

        Assert::same($result->status, Status::Failed);
    }

    public function untaggedTestRunsSynchronously(): void
    {
        $result = TestRunner::runTest([AsyncScenarios::class, 'untaggedRunsSynchronously']);

        Assert::same($result->status, Status::Passed);
    }
}
