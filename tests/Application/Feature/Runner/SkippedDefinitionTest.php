<?php

declare(strict_types=1);

namespace Tests\Application\Feature\Runner;

use Testo\Application\Internal\Runner\TestRunner;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Exception\SkipTest;
use Testo\Core\Value\Status;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner as TestingRunner;
use Tests\Application\Stub\Skipped\FlagSkippedPlugin;
use Tests\Application\Stub\Skipped\SkippedByLocator;

/**
 * The core's own handling of a {@see \Testo\Core\Definition\TestDefinition::$skipped} flag that no
 * interceptor reported on: the runner returns a Skipped result at the end of the pipeline instead
 * of running the body, with a bare reason since none is known.
 */
#[Test]
#[Covers(TestRunner::class)]
#[TestingSuite(path: __DIR__ . '/../../Stub/Skipped', plugins: [FlagSkippedPlugin::class])]
final class SkippedDefinitionTest
{
    public function flaggedTestIsReportedSkippedWithoutRunning(): void
    {
        $result = TestingRunner::runTest([SkippedByLocator::class, 'flagged']);

        Assert::same($result->status, Status::Skipped);
        Assert::instanceOf($result->failure, SkipTest::class);
        Assert::same($result->failure->getMessage(), SkippedByLocator::class . '::flagged is skipped');
        Assert::false(SkippedByLocator::$flaggedRan);
    }

    public function flaggedTestIsCountedAsSkipped(): void
    {
        $result = TestingRunner::runTest([SkippedByLocator::class, 'flagged']);

        Assert::same($result->summary->counts, [Status::Skipped->name => 1]);
    }

    /**
     * `TestStarting` announces a test body; a skipped definition has none, so only the enabled
     * neighbor is announced.
     */
    public function noTestStartingIsDispatchedForAFlaggedTest(): void
    {
        $offset = \count(FlagSkippedPlugin::$started);

        TestingRunner::runTest([SkippedByLocator::class, 'flagged']);

        Assert::array(\array_slice(FlagSkippedPlugin::$started, $offset))
            ->contains('enabled')
            ->notContains('flagged');
    }

    public function enabledNeighborStillRuns(): void
    {
        $result = TestingRunner::runTest([SkippedByLocator::class, 'enabled']);

        Assert::same($result->status, Status::Passed);
    }
}
