<?php

declare(strict_types=1);

namespace Tests\Assert\Feature;

use Testo\Assert;
use Testo\Assert\Internal\Middleware\AssertCollectorInterceptor;
use Testo\Assert\Internal\StaticState;
use Testo\Assert\TestState;
use Testo\Codecov\Covers;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Tests\Assert\Stub\Concurrency\RandomAssertScenarios;
use Tests\Assert\Stub\Concurrency\RevoltAssertScenarios;
use Tests\Assert\Stub\Concurrency\RoundRobinAssertScenarios;

/**
 * The Assert collector is a fiber-local guard ({@see StaticState}), so concurrently running tests each
 * get their own {@see TestState}. These cases drive stub suites whose tests interleave — on Testo's fiber
 * scheduler and on a real Revolt loop — each recording a distinct number of assertions across many
 * suspensions. After the run, each test's own history must hold exactly its own assertions: concurrency
 * must not let one test's assertions bleed into another's history.
 */
#[Test]
#[Covers(StaticState::class)]
#[Covers(AssertCollectorInterceptor::class)]
#[TestingSuite(path: __DIR__ . '/../Stub/Concurrency')]
final class ConcurrentAssertIsolationTest
{
    public function fiberRoundRobinTestsKeepSeparateHistories(): void
    {
        self::assertHistoryCount(
            TestRunner::runTest([RoundRobinAssertScenarios::class, 'recordsThreeAssertions']),
            3,
        );
        self::assertHistoryCount(
            TestRunner::runTest([RoundRobinAssertScenarios::class, 'recordsFourAssertions']),
            4,
        );
        self::assertHistoryCount(
            TestRunner::runTest([RoundRobinAssertScenarios::class, 'recordsFiveAssertions']),
            5,
        );
    }

    public function fiberRandomScheduleKeepsSeparateHistories(): void
    {
        self::assertHistoryCount(
            TestRunner::runTest([RandomAssertScenarios::class, 'recordsThreeAssertions']),
            3,
        );
        self::assertHistoryCount(
            TestRunner::runTest([RandomAssertScenarios::class, 'recordsFourAssertions']),
            4,
        );
        self::assertHistoryCount(
            TestRunner::runTest([RandomAssertScenarios::class, 'recordsFiveAssertions']),
            5,
        );
    }

    public function revoltPerCaseTestsKeepSeparateHistories(): void
    {
        self::assertHistoryCount(
            TestRunner::runTest([RevoltAssertScenarios::class, 'recordsThreeAssertions']),
            3,
        );
        self::assertHistoryCount(
            TestRunner::runTest([RevoltAssertScenarios::class, 'recordsFourAssertions']),
            4,
        );
        self::assertHistoryCount(
            TestRunner::runTest([RevoltAssertScenarios::class, 'recordsFiveAssertions']),
            5,
        );
    }

    /**
     * A test that recorded `$expected` passing assertions must pass and carry exactly that many records in
     * its own {@see TestState} — no more (a sibling's leaked in) and no fewer (its own leaked out).
     */
    private static function assertHistoryCount(TestResult $result, int $expected): void
    {
        Assert::same($result->status, Status::Passed);

        $state = $result->getAttribute(TestState::class);
        Assert::instanceOf($state, TestState::class);
        Assert::count($state->history, $expected);
    }
}
