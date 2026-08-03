<?php

declare(strict_types=1);

namespace Tests\Bridge\Mockery\Feature;

use Testo\Assert;
use Testo\Assert\TestState;
use Testo\Bridge\Mockery\Internal\MockeryInterceptor;
use Testo\Bridge\Mockery\MockeryPlugin;
use Testo\Codecov\Covers;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Tests\Bridge\Mockery\Stub\MockeryAssertConcurrencyScenarios;
use Tests\Bridge\Mockery\Stub\MockeryExpectConcurrencyScenarios;

/**
 * The Mockery bridge must be **transparent** to the rest of the test while tests interleave on the fiber
 * scheduler: the mocks themselves stay isolated (that is the trampoline's own job), and everything else
 * the test does — plain `Assert::*` calls, `Expect::exception()` — must keep landing on that test's own
 * state exactly as it would without Mockery in the case.
 *
 * Each case runs a RoundRobin stub pair through {@see TestRunner} and inspects the per-test results.
 */
#[Test]
#[Covers(MockeryPlugin::class, MockeryInterceptor::class)]
#[TestingSuite(path: __DIR__ . '/../Stub', plugins: [MockeryPlugin::class])]
final class MockeryInterleaveAttributionTest
{
    public function interleavedMocksStayIsolated(): void
    {
        // The trampoline's own concern: each test's expectations verify against its own container even
        // though both tests park mid-mock while the other runs.
        $first = TestRunner::runTest([MockeryAssertConcurrencyScenarios::class, 'firstAssertsAroundItsMock']);
        $second = TestRunner::runTest([MockeryAssertConcurrencyScenarios::class, 'secondAssertsAroundItsMock']);

        Assert::same($first->status, Status::Passed);
        Assert::same($second->status, Status::Passed);
    }

    public function bodyAssertionsLandInEachTestsHistory(): void
    {
        // Each stub test makes two Assert::same() calls of its own; the bridge adds one record for the
        // verified mock. A transparent bridge leaves all three in the test's history.
        $first = TestRunner::runTest([MockeryAssertConcurrencyScenarios::class, 'firstAssertsAroundItsMock']);
        $second = TestRunner::runTest([MockeryAssertConcurrencyScenarios::class, 'secondAssertsAroundItsMock']);

        Assert::same(self::historyCount($first), 3, 'first test: 2 body asserts + 1 mock verification');
        Assert::same(self::historyCount($second), 3, 'second test: 2 body asserts + 1 mock verification');
    }

    public function expectExceptionSurvivesTheInterleave(): void
    {
        // Each stub test declares Expect::exception() up front and throws after its yield — with a
        // transparent bridge both expectations are fulfilled and both tests pass.
        $first = TestRunner::runTest([MockeryExpectConcurrencyScenarios::class, 'firstExpectsItsException']);
        $second = TestRunner::runTest([MockeryExpectConcurrencyScenarios::class, 'secondExpectsItsException']);

        Assert::same($first->status, Status::Passed);
        Assert::same($second->status, Status::Passed);
    }

    private static function historyCount(TestResult $result): int
    {
        $state = $result->getAttribute(TestState::class);

        return $state instanceof TestState ? \count($state->history) : -1;
    }
}
