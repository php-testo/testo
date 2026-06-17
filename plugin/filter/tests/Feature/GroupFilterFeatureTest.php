<?php

declare(strict_types=1);

namespace Tests\Filter\Feature;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\TestResult;
use Testo\Filter\Internal\FilterInterceptor;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Tests\Filter\Unit\Fixture\GroupedTestClass;

/**
 * End-to-end check that the `--group` CLI option, emulated via {@see TestingSuite::$options},
 * flows through the input pipeline into {@see \Testo\Filter\Internal\FilterInput} and drives
 * {@see FilterInterceptor} group filtering across the whole testo run.
 *
 * The fixture {@see GroupedTestClass} carries class-level `integration` plus per-method groups
 * (`db`, `slow`, `fast`). Selecting `group=db` must keep only the `db`-grouped tests.
 */
#[Test]
#[Covers(FilterInterceptor::class)]
#[TestingSuite(
    path: __DIR__ . '/../Unit/Fixture/GroupedTestClass.php',
    options: ['group' => ['db']],
)]
final class GroupFilterFeatureTest
{
    public function dbGroupedTestSurvivesTheGroupFilter(): void
    {
        # The test is in group `db`; it must be present in the run (runTest finds it).
        Assert::instanceOf(TestRunner::runTest([GroupedTestClass::class, 'dbTest']), TestResult::class);
    }

    public function multiGroupedTestSurvivesBecauseItAlsoHasDb(): void
    {
        Assert::instanceOf(TestRunner::runTest([GroupedTestClass::class, 'multiTest']), TestResult::class);
    }

    public function nonDbTestIsFilteredOut(): void
    {
        $this->assertTestDidNotRun('slowTest');
    }

    public function plainTestIsFilteredOut(): void
    {
        $this->assertTestDidNotRun('plainTest');
    }

    /**
     * {@see TestRunner::runTest()} throws when no matching test result is found — i.e. the test
     * was filtered out of the run. Any throwable counts (formatting the missing method into the
     * exception message can itself fail for non-static methods).
     */
    private function assertTestDidNotRun(string $method): void
    {
        try {
            TestRunner::runTest([GroupedTestClass::class, $method]);
        } catch (\Throwable) {
            Assert::true(true);
            return;
        }

        Assert::true(false, "Test `{$method}` should have been filtered out by --group=db but it ran.");
    }
}
