<?php

declare(strict_types=1);

namespace Tests\Skip\Feature;

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
use Testo\Skip\Internal\SkipInterceptor;
use Testo\Skip;

/**
 * Session-level arithmetic for {@see Skip}-marked tests: they are counted in the run's
 * {@see \Testo\Core\Value\Summary}, not lost — and {@see Status::Skipped} never turns a run
 * red on its own.
 */
#[Test]
#[Covers(Skip::class)]
#[Covers(SkipInterceptor::class)]
final class SkipSummaryTest
{
    /**
     * The mixed directory holds one passing, one failing and two skipped tests (one of them
     * data-driven). Skipped tests are where the totals go off by one: they must be counted
     * rather than lost, and the failing neighbor must still fail the run.
     */
    public function skippedTestsAddUpAndFailingNeighborStillFailsTheRun(): void
    {
        $run = self::run(__DIR__ . '/../Stub/SkipSummary/Mixed');

        $summary = $run->summary;
        Assert::same($summary->count(Status::Passed), 1);
        Assert::same($summary->count(Status::Failed), 1);
        Assert::same($summary->count(Status::Skipped), 2);
        # Four tests total: the skipped data-driven one is counted once, not once per data set.
        Assert::same($summary->total(), 4);
        Assert::same($run->status, Status::Failed);
    }

    /**
     * A run consisting only of {@see Skip}-marked tests is a success: {@see Status::Skipped}
     * is neither a success nor a failure, so nothing fails the run.
     *
     * The same run pins one result per skipped test. Every `#[Skip]` occurrence of the case
     * spawns its own {@see SkipInterceptor} through the fallback alias; a second delivery would
     * show up here as an inflated total and an extra name.
     */
    public function runOfOnlySkippedTestsIsSuccessfulAndDeliveredOnce(): void
    {
        $run = self::run(__DIR__ . '/../Stub/SkipSummary/OnlySkipped');

        Assert::same($run->status, Status::Passed);
        Assert::same($run->summary->count(Status::Skipped), 2);
        Assert::same($run->summary->total(), 2);
        $cases = [];
        foreach ($run as $suite) {
            foreach ($suite as $case) {
                $cases[] = $case;
            }
        }
        # The directory holds one class with two skipped tests.
        Assert::count($cases, 1);
        $names = \array_map(
            static fn(TestResult $result): string => $result->info->name,
            \iterator_to_array($cases[0], preserve_keys: false),
        );
        \sort($names);
        Assert::same($names, ['firstSkipped', 'secondSkipped']);
    }

    private static function run(string $path): RunResult
    {
        return Application::createFromConfig(new ApplicationConfig(
            src: [],
            suites: [
                new SuiteConfig(
                    'SkipSummary',
                    location: new FinderConfig(include: [$path]),
                ),
            ],
        ))->run();
    }
}
