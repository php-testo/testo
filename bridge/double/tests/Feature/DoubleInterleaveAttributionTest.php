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
use Testo\Filter\Group;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Tests\Bridge\Double\Stub\DoubleAssertConcurrencyScenarios;
use Tests\Bridge\Double\Stub\DoubleExpectConcurrencyScenarios;

/**
 * The Double bridge must be **transparent** to the rest of the test while tests interleave on the fiber
 * scheduler: the doubles themselves stay isolated (that is the trampoline's own job), and everything else
 * the test does — plain `Assert::*` calls, `Expect::exception()` — must keep landing on that test's own
 * state exactly as it would without Double in the case.
 *
 * Each case runs a RoundRobin stub pair through {@see TestRunner} and inspects the per-test results.
 */
#[Test]
#[Group('async')]
#[Covers(DoublePlugin::class)]
#[Covers(DoubleInterceptor::class)]
#[TestingSuite(path: __DIR__ . '/../Stub', plugins: [DoublePlugin::class])]
final class DoubleInterleaveAttributionTest
{
    public function interleavedDoublesStayIsolated(): void
    {
        // The trampoline's own concern: each test's expectations verify against its own pending list
        // even though both tests park mid-double while the other runs.
        $first = TestRunner::runTest([DoubleAssertConcurrencyScenarios::class, 'firstAssertsAroundItsDouble']);
        $second = TestRunner::runTest([DoubleAssertConcurrencyScenarios::class, 'secondAssertsAroundItsDouble']);

        Assert::same($first->status, Status::Passed);
        Assert::same($second->status, Status::Passed);
    }

    public function bodyAssertionsLandInEachTestsHistory(): void
    {
        // Each stub test makes two Assert::same() calls of its own; the bridge adds one record for the
        // verified double. A transparent bridge leaves all three in the test's history.
        $first = TestRunner::runTest([DoubleAssertConcurrencyScenarios::class, 'firstAssertsAroundItsDouble']);
        $second = TestRunner::runTest([DoubleAssertConcurrencyScenarios::class, 'secondAssertsAroundItsDouble']);

        Assert::same(self::historyCount($first), 3, 'first test: 2 body asserts + 1 double verification');
        Assert::same(self::historyCount($second), 3, 'second test: 2 body asserts + 1 double verification');
    }

    public function expectExceptionSurvivesTheInterleave(): void
    {
        // Each stub test declares Expect::exception() up front and throws after its yield — with a
        // transparent bridge both expectations are fulfilled and both tests pass.
        $first = TestRunner::runTest([DoubleExpectConcurrencyScenarios::class, 'firstExpectsItsException']);
        $second = TestRunner::runTest([DoubleExpectConcurrencyScenarios::class, 'secondExpectsItsException']);

        Assert::same($first->status, Status::Passed);
        Assert::same($second->status, Status::Passed);
    }

    private static function historyCount(TestResult $result): int
    {
        $state = $result->getAttribute(TestState::class);

        return $state instanceof TestState ? \count($state->history) : -1;
    }
}
