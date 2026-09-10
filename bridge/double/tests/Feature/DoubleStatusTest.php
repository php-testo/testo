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

    public function verifiedChecksNameTheDoubleAndMethod(): void
    {
        $result = TestRunner::runTest([DoubleScenarios::class, 'receivedVerificationOnly']);
        Assert::string(self::successExpectations($result))
            ->contains('Double `Countable`')
            ->contains('count()');
    }

    public function doubleAndAssertCoexist(): void
    {
        $result = TestRunner::runTest([DoubleScenarios::class, 'doubleAndAssertMixed']);
        Assert::same($result->status, Status::Passed);
    }

    public function checksAreRecordedInChronologicalOrder(): void
    {
        // Checks are recorded the moment they resolve, not batched at teardown.
        $result = TestRunner::runTest([DoubleScenarios::class, 'checkInterleavesWithAssertions']);
        $order = self::orderedExpectations($result);

        $firstDouble = null;
        foreach ($order as $i => $expectation) {
            if (\str_contains($expectation, 'Double `')) {
                $firstDouble = $i;
                break;
            }
        }
        Assert::true($firstDouble !== null, 'a Double check was recorded');

        $assertionAfterDouble = false;
        foreach ($order as $i => $expectation) {
            if ($i > $firstDouble && !\str_contains($expectation, 'Double `')) {
                $assertionAfterDouble = true;
                break;
            }
        }
        Assert::true($assertionAfterDouble, 'a plain assertion is recorded after a Double check');
    }

    public function bodyCheckFailureIsRecordedButResultLeftAsIs(): void
    {
        // The bridge records a body-thrown check failure but leaves the runner's Error result untouched.
        $result = TestRunner::runTest([DoubleScenarios::class, 'bodyCheckFailsUncaught']);
        Assert::same($result->status, Status::Error);
        Assert::true(self::hasRecord($result, success: false));
        Assert::string(self::failReason($result))->contains('expected no calls');
    }

    public function stateIsDrainedAfterAFailingTest(): void
    {
        // The second test would fail too if the first one's unmet expectation leaked past the failure path.
        $failed = TestRunner::runTest([DoubleResetScenarios::class, 'leavesUnmetExpectation']);
        Assert::same($failed->status, Status::Failed);

        $next = TestRunner::runTest([DoubleResetScenarios::class, 'seesCleanSlate']);
        Assert::same($next->status, Status::Passed);
    }

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

    /**
     * @return list<string>
     */
    private static function orderedExpectations(TestResult $result): array
    {
        $state = $result->getAttribute(TestState::class);
        if (!$state instanceof TestState) {
            return [];
        }

        return \array_map(
            static fn(\Stringable $record): string => (string) $record,
            $state->history,
        );
    }

    private static function successExpectations(TestResult $result): string
    {
        $state = $result->getAttribute(TestState::class);
        if (!$state instanceof TestState) {
            return '';
        }

        $expectations = [];
        foreach ($state->history as $record) {
            $record->isSuccess() and $expectations[] = (string) $record;
        }

        return \implode("\n", $expectations);
    }

    private static function failReason(TestResult $result): string
    {
        $state = $result->getAttribute(TestState::class);
        if (!$state instanceof TestState) {
            return '';
        }

        foreach ($state->history as $record) {
            if (!$record->isSuccess()) {
                return $record->getFailReason();
            }
        }

        return '';
    }
}
