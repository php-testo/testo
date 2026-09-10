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
 * While tests interleave on the fiber scheduler, plain `Assert::*` calls and `Expect::exception()` must
 * keep landing on their own test's state as if Double were not in the case. Each case runs a RoundRobin
 * stub pair through {@see TestRunner}.
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
        $first = TestRunner::runTest([DoubleAssertConcurrencyScenarios::class, 'firstAssertsAroundItsDouble']);
        $second = TestRunner::runTest([DoubleAssertConcurrencyScenarios::class, 'secondAssertsAroundItsDouble']);

        Assert::same($first->status, Status::Passed);
        Assert::same($second->status, Status::Passed);
    }

    public function bodyAssertionsLandInEachTestsHistory(): void
    {
        $first = TestRunner::runTest([DoubleAssertConcurrencyScenarios::class, 'firstAssertsAroundItsDouble']);
        $second = TestRunner::runTest([DoubleAssertConcurrencyScenarios::class, 'secondAssertsAroundItsDouble']);

        Assert::same(self::historyCount($first), 3, 'first test: 2 body asserts + 1 double verification');
        Assert::same(self::historyCount($second), 3, 'second test: 2 body asserts + 1 double verification');
    }

    public function expectExceptionSurvivesTheInterleave(): void
    {
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
