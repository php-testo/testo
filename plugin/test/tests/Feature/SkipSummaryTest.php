<?php

declare(strict_types=1);

namespace Tests\Test\Feature;

use Testo\Application\Application;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\RunResult;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Test;
use Testo\Test\Internal\SkipInterceptor;
use Testo\Test\Skip;

/**
 * Session-level arithmetic for parked tests: they are counted, not lost — and they never
 * turn a run red on their own.
 */
#[Test]
#[Covers(Skip::class)]
#[Covers(SkipInterceptor::class)]
final class SkipSummaryTest
{
    /**
     * The mixed catalog holds one passing, one failing and two parked tests (one of them
     * data-driven). The classic off-by-parked bug: totals must satisfy
     * `total = passed + failed + skipped` with the data-driven parked test counted exactly
     * once — and the failing neighbor still fails the run.
     */
    public function parkedTestsAddUpAndFailingNeighborStillFailsTheRun(): void
    {
        $result = self::run(__DIR__ . '/../Stub/SkipSummary/Mixed');

        $summary = $result->summary;
        Assert::same($summary->count(Status::Passed), 1);
        Assert::same($summary->count(Status::Failed), 1);
        Assert::same($summary->count(Status::Skipped), 2);
        Assert::same(
            $summary->total(),
            $summary->passed() + $summary->failed() + $summary->count(Status::Skipped),
        );
        Assert::same($result->status, Status::Failed);
    }

    /**
     * A run consisting only of `#[Skip]`-marked tests is a success: Skipped is neither a
     * success nor a failure, so nothing fails the run.
     *
     * The same run pins the dedup invariant: with `TestPlugin` registered, a class-level
     * `#[Skip]` also spawns a fallback instance of the interceptor, and the conflict policy
     * must collapse the duplicate — each parked test yields exactly one result, not one per
     * delivery path.
     */
    public function runOfOnlyParkedTestsIsSuccessfulAndDeliveredOnce(): void
    {
        $run = self::run(__DIR__ . '/../Stub/SkipSummary/OnlyParked');

        Assert::same($run->status, Status::Passed);
        Assert::same($run->summary->count(Status::Skipped), 2);
        Assert::same($run->summary->total(), 2);
        $cases = [];
        foreach ($run as $suite) {
            foreach ($suite as $case) {
                $cases[] = $case;
            }
        }
        # The catalog holds one class with two parked tests.
        Assert::count($cases, 1);
        $names = \array_map(
            static fn(TestResult $result): string => $result->info->name,
            \iterator_to_array($cases[0], preserve_keys: false),
        );
        \sort($names);
        Assert::same($names, ['firstParked', 'secondParked']);
    }

    private static function run(string $catalog): RunResult
    {
        return Application::createFromConfig(new ApplicationConfig(
            src: [],
            suites: [
                new SuiteConfig(
                    'SkipSummary',
                    location: new FinderConfig(include: [$catalog]),
                ),
            ],
        ))->run();
    }
}
