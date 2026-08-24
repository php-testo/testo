<?php

declare(strict_types=1);

namespace Tests\Bridge\Double\Feature;

use Testo\Assert;
use Testo\Assert\TestState;
use Testo\Bridge\Double\DoublePlugin;
use Testo\Bridge\Double\Internal\DoubleInterceptor;
use Testo\Codecov\Covers;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Tests\Bridge\Double\Stub\DoubleResetScenarios;
use Tests\Bridge\Double\Stub\DoubleScenarios;

/**
 * How the Double bridge maps double verification onto Testo's test statuses. Each case runs a stub
 * scenario through {@see TestRunner} (with {@see DoublePlugin} loaded) and asserts the resulting
 * {@see Status} — the surface a user sees in the report.
 */
#[Test]
#[Covers(DoublePlugin::class)]
#[Covers(DoubleInterceptor::class)]
#[TestingSuite(path: __DIR__ . '/../Stub', plugins: [DoublePlugin::class])]
final class DoubleStatusTest
{
    public function fulfilledExpectationCountsAsAssertion(): void
    {
        $result = TestRunner::runTest([DoubleScenarios::class, 'fulfilledExpectationOnly']);
        Assert::same($result->status, Status::Passed);
        Assert::true(self::hasRecord($result, success: true));
    }

    public function unfulfilledExpectationFailsTheTest(): void
    {
        $result = TestRunner::runTest([DoubleScenarios::class, 'unfulfilledExpectation']);
        Assert::same($result->status, Status::Failed);
        Assert::true(self::hasRecord($result, success: false));
    }

    public function noDoublesNoAssertionsStaysRisky(): void
    {
        $result = TestRunner::runTest([DoubleScenarios::class, 'noDoublesNoAssertions']);
        Assert::same($result->status, Status::Risky);
    }

    public function receivedVerificationCountsAsAssertion(): void
    {
        $result = TestRunner::runTest([DoubleScenarios::class, 'receivedVerificationOnly']);
        Assert::same($result->status, Status::Passed);
    }

    public function doubleAndAssertCoexist(): void
    {
        $result = TestRunner::runTest([DoubleScenarios::class, 'doubleAndAssertMixed']);
        Assert::same($result->status, Status::Passed);
    }

    public function stateIsDrainedAfterAFailingTest(): void
    {
        // leavesUnmetExpectation fails on verifyAll(); seesCleanSlate runs right after it and would
        // fail too if that unmet expectation had leaked into the global pending list. Both statuses
        // being as expected proves the drain happens on the failure path, not only when a test passes.
        $failed = TestRunner::runTest([DoubleResetScenarios::class, 'leavesUnmetExpectation']);
        Assert::same($failed->status, Status::Failed);

        $next = TestRunner::runTest([DoubleResetScenarios::class, 'seesCleanSlate']);
        Assert::same($next->status, Status::Passed);
    }

    /**
     * Whether the test's assertion history holds a record with the given success flag — i.e. the
     * bridge reported the double verification (fulfilled or failed) to the Assert plugin.
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
