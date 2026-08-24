<?php

declare(strict_types=1);

namespace Tests\Bridge\Mockery\Feature;

use Testo\Assert;
use Testo\Bridge\Mockery\Internal\MockeryInterceptor;
use Testo\Bridge\Mockery\MockeryPlugin;
use Testo\Codecov\Covers;
use Testo\Assert\TestState;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Tests\Bridge\Mockery\Stub\MockeryResetScenarios;
use Tests\Bridge\Mockery\Stub\MockeryScenarios;

/**
 * How the Mockery bridge maps mock verification onto Testo's test statuses. Each case runs a stub
 * scenario through {@see TestRunner} (with {@see MockeryPlugin} loaded) and asserts the resulting
 * {@see Status} — the surface a user sees in the report.
 */
#[Test]
#[Covers(MockeryPlugin::class)]
#[Covers(MockeryInterceptor::class)]
#[TestingSuite(path: __DIR__ . '/../Stub', plugins: [MockeryPlugin::class])]
final class MockeryStatusTest
{
    public function fulfilledMockCountsAsAssertion(): void
    {
        $result = TestRunner::runTest([MockeryScenarios::class, 'fulfilledExpectationOnly']);
        Assert::same($result->status, Status::Passed);
        Assert::true(self::hasRecord($result, success: true));
    }

    public function unfulfilledExpectationFailsTheTest(): void
    {
        $result = TestRunner::runTest([MockeryScenarios::class, 'unfulfilledExpectation']);
        Assert::same($result->status, Status::Failed);
        Assert::true(self::hasRecord($result, success: false));
    }

    public function noMocksNoAssertionsStaysRisky(): void
    {
        $result = TestRunner::runTest([MockeryScenarios::class, 'noMocksNoAssertions']);
        Assert::same($result->status, Status::Risky);
    }

    public function spyVerificationCountsAsAssertion(): void
    {
        $result = TestRunner::runTest([MockeryScenarios::class, 'spyVerificationOnly']);
        Assert::same($result->status, Status::Passed);
    }

    public function mockAndAssertCoexist(): void
    {
        $result = TestRunner::runTest([MockeryScenarios::class, 'mockAndAssertMixed']);
        Assert::same($result->status, Status::Passed);
    }

    public function stateIsResetAfterAFailingTest(): void
    {
        // leavesUnmetExpectation fails on close(); seesCleanContainer runs right after it and would
        // fail too if that close() had not cleared the container. Both being reported as expected
        // proves the reset happens on the failure path, not only when a test passes.
        $failed = TestRunner::runTest([MockeryResetScenarios::class, 'leavesUnmetExpectation']);
        Assert::same($failed->status, Status::Failed);

        $next = TestRunner::runTest([MockeryResetScenarios::class, 'seesCleanContainer']);
        Assert::same($next->status, Status::Passed);
    }

    /**
     * Whether the test's assertion history holds a record with the given success flag — i.e. the
     * bridge reported the mock verification (fulfilled or failed) to the Assert plugin.
     */
    private static function hasRecord(TestResult $result, bool $success): bool
    {
        $state = $result->getAttribute(TestState::class);
        if (!$state instanceof TestState) {
            return false;
        }

        foreach ($state->history as $record) {
            if ($record->isSuccess() === $success) {
                return true;
            }
        }

        return false;
    }
}
