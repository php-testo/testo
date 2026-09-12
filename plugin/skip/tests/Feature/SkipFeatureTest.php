<?php

declare(strict_types=1);

namespace Tests\Skip\Feature;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Exception\SkipTest;
use Testo\Core\Value\Status;
use Testo\Data\MultipleResult;
use Testo\Filter\Group;
use Testo\Test;
use Testo\Skip\Internal\SkipInterceptor;
use Testo\Skip;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Tests\Skip\Stub\PipelineEntrySpyPlugin;
use Tests\Skip\Stub\Skip\SkipChildStub;
use Tests\Skip\Stub\Skip\SkipClassAndMethodStub;
use Tests\Skip\Stub\Skip\SkipClassLevelStub;
use Tests\Skip\Stub\Skip\SkipConstructorSpyStub;
use Tests\Skip\Stub\Skip\SkipInFiberStub;
use Tests\Skip\Stub\Skip\SkipMethodStub;
use Tests\Skip\Stub\Skip\SkipNonStaticHookStub;
use Tests\Skip\Stub\Skip\SkipOverridingMethodStub;
use Tests\Skip\Stub\Skip\SkipTraitStub;
use Tests\Skip\Stub\Skip\SkipWithDataProviderStub;
use Tests\Skip\Stub\Skip\SkipWithHooksStub;
use Tests\Skip\Stub\Skip\SkipWithRepeatStub;
use Tests\Skip\Stub\Skip\SkipWithRetryStub;

/**
 * End-to-end checks that {@see SkipInterceptor}, wired by the attribute's fallback declaration,
 * deactivates the `#[Skip]`-marked tests of a case before it runs and delivers them back as
 * {@see Status::Skipped} results carrying the composed skip message.
 *
 * Every test method replays the whole `Stub/Skip` directory through {@see TestRunner} and then inspects
 * either the returned result or what the stubs recorded. The stubs' static counters and flags
 * survive those runs, so a check either takes a delta over its own run or pins a value that must
 * never move at all. The directory holds a `#[RunInFiber]` stub, executed by every one of those
 * runs — hence the class-level `#[Group('async')]`.
 */
#[Test]
#[Group('async')]
#[TestingSuite(path: __DIR__ . '/../Stub/Skip', plugins: [PipelineEntrySpyPlugin::class])]
#[Covers(Skip::class)]
#[Covers(SkipInterceptor::class)]
final class SkipFeatureTest
{
    public function __construct()
    {
        # Functions are not autoloadable: load the stub so TestRunner::runTest() can resolve the
        # function names below regardless of which test runs first. The pipeline re-includes the
        # same file (include_once) when it runs.
        require_once __DIR__ . '/../Stub/Skip/skip_functions.php';
    }

    public function methodLevelSkipReportsSkippedWithComposedReason(): void
    {
        $result = TestRunner::runTest([SkipMethodStub::class, 'skipped']);

        Assert::same($result->status, Status::Skipped);
        Assert::instanceOf($result->failure, SkipTest::class);
        Assert::same(
            $result->failure?->getMessage(),
            SkipMethodStub::class . '::skipped is skipped via #[Skip] ==> broken by the pricing rework, see ISSUE-123',
        );
    }

    public function emptyReasonFallsBackToGeneratedMessage(): void
    {
        $result = TestRunner::runTest([SkipMethodStub::class, 'skippedNoReason']);

        Assert::same($result->status, Status::Skipped);
        Assert::same(
            $result->failure?->getMessage(),
            SkipMethodStub::class . '::skippedNoReason is skipped via #[Skip]',
        );
    }

    public function controlNeighborNextToSkippedTestsStillRuns(): void
    {
        $result = TestRunner::runTest([SkipMethodStub::class, 'enabled']);

        Assert::same($result->status, Status::Passed);
    }

    public function classLevelSkipSkipsEveryTestWithClassReason(): void
    {
        $first = TestRunner::runTest([SkipClassLevelStub::class, 'firstSkipped']);
        $second = TestRunner::runTest([SkipClassLevelStub::class, 'secondSkipped']);

        Assert::same($first->status, Status::Skipped);
        Assert::same($second->status, Status::Skipped);
        Assert::true(\str_ends_with((string) $first->failure?->getMessage(), ' ==> the whole case is skipped'));
        Assert::true(\str_ends_with((string) $second->failure?->getMessage(), ' ==> the whole case is skipped'));
    }

    public function methodReasonWinsOverClassReason(): void
    {
        $own = TestRunner::runTest([SkipClassAndMethodStub::class, 'ownReason']);
        $inherited = TestRunner::runTest([SkipClassAndMethodStub::class, 'classReason']);

        Assert::true(\str_ends_with((string) $own->failure?->getMessage(), ' ==> method-specific reason'));
        Assert::true(\str_ends_with((string) $inherited->failure?->getMessage(), ' ==> class-wide reason'));
    }

    /**
     * The method-level attribute wins as a whole: an empty method reason is not filled in
     * from the class reason.
     */
    public function emptyMethodReasonStillWinsOverClassReason(): void
    {
        $result = TestRunner::runTest([SkipClassAndMethodStub::class, 'emptyOwnReason']);

        Assert::same($result->status, Status::Skipped);
        Assert::same(
            $result->failure?->getMessage(),
            SkipClassAndMethodStub::class . '::emptyOwnReason is skipped via #[Skip]',
        );
    }

    public function functionalTestUsesFunctionFqnInMessage(): void
    {
        $result = TestRunner::runTest('Tests\Skip\Stub\Skip\skippedFunction');

        Assert::same($result->status, Status::Skipped);
        Assert::same(
            $result->failure?->getMessage(),
            'Tests\Skip\Stub\Skip\skippedFunction is skipped via #[Skip] ==> functional test is skipped',
        );
    }

    /**
     * The function-based analog of the control neighbor: an enabled function of a partially
     * skipped file still runs through the batch runner the interceptor installs on the case, and
     * passes.
     */
    public function controlNeighborFunctionNextToSkippedFunctionStillRuns(): void
    {
        $result = TestRunner::runTest('Tests\Skip\Stub\Skip\enabledFunction');

        Assert::same($result->status, Status::Passed);
    }

    /**
     * The origin contract for downstream consumers: a result skipped by `#[Skip]` carries the
     * attribute instances in `$result->info`, unlike a runtime `throw SkipTest` skip.
     */
    public function skippedResultCarriesOriginAttribute(): void
    {
        $result = TestRunner::runTest([SkipMethodStub::class, 'skipped']);

        $origin = $result->info->getAttribute(Skip::class);
        Assert::array($origin)->hasCount(1);
        Assert::instanceOf($origin[0], Skip::class);
    }

    /**
     * The skipped test is filtered out before the case runs: class-level hooks fire as usual
     * (once per directory run), per-test hooks fire only for the enabled control test.
     */
    public function classHooksRunButTestHooksDoNot(): void
    {
        $beforeClass = SkipWithHooksStub::$beforeClassCalls;
        $afterClass = SkipWithHooksStub::$afterClassCalls;
        $beforeTest = SkipWithHooksStub::$beforeTestCalls;
        $afterTest = SkipWithHooksStub::$afterTestCalls;

        $result = TestRunner::runTest([SkipWithHooksStub::class, 'skipped']);

        Assert::same($result->status, Status::Skipped);
        Assert::same(SkipWithHooksStub::$beforeClassCalls - $beforeClass, 1);
        Assert::same(SkipWithHooksStub::$afterClassCalls - $afterClass, 1);
        # Only the enabled control test of the case went through the per-test pipeline.
        Assert::same(SkipWithHooksStub::$beforeTestCalls - $beforeTest, 1);
        Assert::same(SkipWithHooksStub::$afterTestCalls - $afterTest, 1);
    }

    public function fullySkippedCaseWithoutHooksIsNeverInstantiated(): void
    {
        $result = TestRunner::runTest([SkipConstructorSpyStub::class, 'firstSkipped']);

        Assert::same($result->status, Status::Skipped);
        Assert::false(SkipConstructorSpyStub::$constructed);
    }

    /**
     * Documented caveat: a non-static class-level hook builds the class even when every
     * test is skipped — pinned so a future change is conscious, not accidental.
     */
    public function nonStaticClassHookStillBuildsTheClass(): void
    {
        $constructions = SkipNonStaticHookStub::$constructions;

        $result = TestRunner::runTest([SkipNonStaticHookStub::class, 'skipped']);

        Assert::same($result->status, Status::Skipped);
        Assert::same(SkipNonStaticHookStub::$constructions - $constructions, 1);
    }

    public function classLevelSkipIsInheritedFromParent(): void
    {
        $result = TestRunner::runTest([SkipChildStub::class, 'skipped']);

        Assert::same($result->status, Status::Skipped);
        Assert::true(\str_ends_with((string) $result->failure?->getMessage(), ' ==> inherited from the parent class'));
    }

    public function classLevelSkipIsInheritedFromTrait(): void
    {
        $result = TestRunner::runTest([SkipTraitStub::class, 'skipped']);

        Assert::same($result->status, Status::Skipped);
        Assert::true(\str_ends_with((string) $result->failure?->getMessage(), ' ==> inherited from the trait'));
    }

    /**
     * A method-level `#[Skip]` follows the prototype chain like `#[Group]` does: an overriding
     * method without the attribute is still skipped, with the parent's reason.
     */
    public function methodLevelSkipIsInheritedByOverridingMethod(): void
    {
        $result = TestRunner::runTest([SkipOverridingMethodStub::class, 'skipped']);

        Assert::same($result->status, Status::Skipped);
        Assert::true(\str_ends_with((string) $result->failure?->getMessage(), ' ==> inherited from the overridden method'));
    }

    /**
     * A data-driven skipped test yields a single Skipped node: the provider is never called
     * (not once across all directory runs of this class), no `MultipleResult` aggregate is
     * attached.
     */
    public function dataProviderIsNotCalledForSkippedTest(): void
    {
        $result = TestRunner::runTest([SkipWithDataProviderStub::class, 'skipped']);

        Assert::same($result->status, Status::Skipped);
        Assert::null($result->getAttribute(MultipleResult::class));
        Assert::same(SkipWithDataProviderStub::$providerCalls, 0);
    }

    /**
     * The positive control on the enabled neighbor proves that `#[Retry]` does engage in this
     * run — its first attempt fails and the second passes — so the zero on the skipped test is
     * the skip at work, not a retry plugin that never ran.
     */
    public function retryDoesNotEngageForSkippedTest(): void
    {
        $attempts = SkipWithRetryStub::$attempts;
        $enabledAttempts = SkipWithRetryStub::$enabledAttempts;

        $result = TestRunner::runTest([SkipWithRetryStub::class, 'skipped']);

        Assert::same($result->status, Status::Skipped);
        Assert::same(SkipWithRetryStub::$attempts - $attempts, 0);
        Assert::same(SkipWithRetryStub::$enabledAttempts - $enabledAttempts, 2);
    }

    /**
     * Same shape for `#[Repeat]`: the enabled neighbor runs all three of its repetitions, the
     * skipped test not even once.
     */
    public function repeatDoesNotEngageForSkippedTest(): void
    {
        $enabledRuns = SkipWithRepeatStub::$enabledRuns;

        $result = TestRunner::runTest([SkipWithRepeatStub::class, 'skipped']);

        Assert::same($result->status, Status::Skipped);
        Assert::false(SkipWithRepeatStub::$bodyRan);
        Assert::same(SkipWithRepeatStub::$enabledRuns - $enabledRuns, 3);
    }

    /**
     * The common ground of the hook/provider/retry/repeat checks above: a skipped test never
     * enters the per-test pipeline at all. A spy interceptor on that pipeline sees the
     * enabled neighbors of the directory and none of the skipped tests.
     */
    public function skippedTestsNeverEnterThePerTestPipeline(): void
    {
        $offset = \count(PipelineEntrySpyPlugin::$entered);

        TestRunner::runTest([SkipMethodStub::class, 'skipped']);

        $entered = \array_slice(PipelineEntrySpyPlugin::$entered, $offset);
        Assert::array($entered)
            ->contains(SkipMethodStub::class . '::enabled')
            ->contains('Tests\Skip\Stub\Skip\enabledFunction')
            ->notContains(SkipMethodStub::class . '::skipped')
            ->notContains(SkipMethodStub::class . '::skippedNoReason')
            ->notContains(SkipWithHooksStub::class . '::skipped')
            ->notContains(SkipWithDataProviderStub::class . '::skipped')
            ->notContains(SkipWithRetryStub::class . '::skipped')
            ->notContains(SkipWithRepeatStub::class . '::skipped')
            ->notContains(SkipInFiberStub::class . '::skipped')
            ->notContains(SkipOverridingMethodStub::class . '::skipped')
            ->notContains('Tests\Skip\Stub\Skip\skippedFunction');
    }

    /**
     * Fiber compatibility: the skip interceptor wraps the fiber batch runner instead of
     * replacing it. The round-robin interleaving of the two enabled tests is produced only by
     * the case scheduler — run sequentially, their `\Fiber::suspend()` would throw and the
     * log would stop short — while the skipped test is still skipped.
     */
    public function fiberBatchRunnerSurvivesTheWrap(): void
    {
        $offset = \count(SkipInFiberStub::$log);

        $skipped = TestRunner::runTest([SkipInFiberStub::class, 'skipped']);

        Assert::same($skipped->status, Status::Skipped);
        Assert::same(
            \array_slice(SkipInFiberStub::$log, $offset),
            ['first.1', 'second.1', 'first.2', 'second.2'],
        );
    }
}
