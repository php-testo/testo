<?php

declare(strict_types=1);

namespace Tests\Test\Feature;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Exception\SkipTest;
use Testo\Core\Value\Status;
use Testo\Data\MultipleResult;
use Testo\Test;
use Testo\Test\Internal\SkipInterceptor;
use Testo\Test\Skip;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Tests\Test\Stub\PipelineEntrySpyPlugin;
use Tests\Test\Stub\Skip\SkipChildStub;
use Tests\Test\Stub\Skip\SkipClassAndMethodStub;
use Tests\Test\Stub\Skip\SkipClassLevelStub;
use Tests\Test\Stub\Skip\SkipConstructorSpyStub;
use Tests\Test\Stub\Skip\SkipInFiberStub;
use Tests\Test\Stub\Skip\SkipMethodStub;
use Tests\Test\Stub\Skip\SkipNonStaticHookStub;
use Tests\Test\Stub\Skip\SkipTraitStub;
use Tests\Test\Stub\Skip\SkipWithDataProviderStub;
use Tests\Test\Stub\Skip\SkipWithHooksStub;
use Tests\Test\Stub\Skip\SkipWithRepeatStub;
use Tests\Test\Stub\Skip\SkipWithRetryStub;

#[Test]
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
        $result = TestRunner::runTest([SkipMethodStub::class, 'parked']);

        Assert::same($result->status, Status::Skipped);
        Assert::instanceOf($result->failure, SkipTest::class);
        Assert::same(
            $result->failure?->getMessage(),
            SkipMethodStub::class . '::parked is skipped via #[Skip] ==> broken by the pricing rework, see ISSUE-123',
        );
    }

    public function emptyReasonFallsBackToGeneratedMessage(): void
    {
        $result = TestRunner::runTest([SkipMethodStub::class, 'parkedNoReason']);

        Assert::same($result->status, Status::Skipped);
        Assert::same(
            $result->failure?->getMessage(),
            SkipMethodStub::class . '::parkedNoReason is skipped via #[Skip]',
        );
    }

    public function controlNeighborNextToParkedTestsStillRuns(): void
    {
        $result = TestRunner::runTest([SkipMethodStub::class, 'enabled']);

        Assert::same($result->status, Status::Passed);
    }

    public function classLevelSkipParksEveryTestWithClassReason(): void
    {
        $first = TestRunner::runTest([SkipClassLevelStub::class, 'firstParked']);
        $second = TestRunner::runTest([SkipClassLevelStub::class, 'secondParked']);

        Assert::same($first->status, Status::Skipped);
        Assert::same($second->status, Status::Skipped);
        Assert::true(\str_ends_with((string) $first->failure?->getMessage(), ' ==> the whole case is parked'));
        Assert::true(\str_ends_with((string) $second->failure?->getMessage(), ' ==> the whole case is parked'));
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
        $result = TestRunner::runTest('Tests\Test\Stub\Skip\parked_function');

        Assert::same($result->status, Status::Skipped);
        Assert::same(
            $result->failure?->getMessage(),
            'Tests\Test\Stub\Skip\parked_function is skipped via #[Skip] ==> functional test is parked',
        );
    }

    /**
     * The function-based analog of the control neighbor: an enabled function of a partially
     * parked file still runs through the wrapped batch runner and passes.
     */
    public function controlNeighborFunctionNextToParkedFunctionStillRuns(): void
    {
        $result = TestRunner::runTest('Tests\Test\Stub\Skip\enabled_function');

        Assert::same($result->status, Status::Passed);
    }

    /**
     * The origin contract for downstream consumers: a `#[Skip]`-parked result carries the
     * attribute instances in `$result->info`, unlike a runtime `throw SkipTest` skip.
     */
    public function parkedResultCarriesOriginAttribute(): void
    {
        $result = TestRunner::runTest([SkipMethodStub::class, 'parked']);

        $origin = $result->info->getAttribute(Skip::class);
        Assert::true(\is_array($origin));
        Assert::count($origin, 1);
        Assert::instanceOf($origin[0], Skip::class);
    }

    /**
     * The parked test is filtered out before the case runs: class-level hooks fire as usual
     * (once per catalog run), per-test hooks fire only for the enabled control test.
     */
    public function classHooksRunButTestHooksDoNot(): void
    {
        $beforeClass = SkipWithHooksStub::$beforeClass;
        $afterClass = SkipWithHooksStub::$afterClass;
        $beforeTest = SkipWithHooksStub::$beforeTest;
        $afterTest = SkipWithHooksStub::$afterTest;

        $result = TestRunner::runTest([SkipWithHooksStub::class, 'parked']);

        Assert::same($result->status, Status::Skipped);
        Assert::same(SkipWithHooksStub::$beforeClass - $beforeClass, 1);
        Assert::same(SkipWithHooksStub::$afterClass - $afterClass, 1);
        # Only the enabled control test of the case went through the per-test pipeline.
        Assert::same(SkipWithHooksStub::$beforeTest - $beforeTest, 1);
        Assert::same(SkipWithHooksStub::$afterTest - $afterTest, 1);
    }

    public function fullyParkedCaseWithoutHooksIsNeverInstantiated(): void
    {
        $result = TestRunner::runTest([SkipConstructorSpyStub::class, 'firstParked']);

        Assert::same($result->status, Status::Skipped);
        Assert::false(SkipConstructorSpyStub::$constructed);
    }

    /**
     * Documented caveat: a non-static class-level hook builds the class even when every
     * test is parked — pinned so a future change is conscious, not accidental.
     */
    public function nonStaticClassHookStillBuildsTheClass(): void
    {
        $constructions = SkipNonStaticHookStub::$constructions;

        $result = TestRunner::runTest([SkipNonStaticHookStub::class, 'parked']);

        Assert::same($result->status, Status::Skipped);
        Assert::same(SkipNonStaticHookStub::$constructions - $constructions, 1);
    }

    public function classLevelSkipIsInheritedFromParent(): void
    {
        $result = TestRunner::runTest([SkipChildStub::class, 'parked']);

        Assert::same($result->status, Status::Skipped);
        Assert::true(\str_ends_with((string) $result->failure?->getMessage(), ' ==> inherited from the parent class'));
    }

    public function classLevelSkipIsInheritedFromTrait(): void
    {
        $result = TestRunner::runTest([SkipTraitStub::class, 'parked']);

        Assert::same($result->status, Status::Skipped);
        Assert::true(\str_ends_with((string) $result->failure?->getMessage(), ' ==> inherited from the trait'));
    }

    /**
     * A data-driven parked test yields a single Skipped node: the provider is never called
     * (not once across all catalog runs of this class), no `MultipleResult` aggregate is
     * attached.
     */
    public function dataProviderIsNotCalledForParkedTest(): void
    {
        $result = TestRunner::runTest([SkipWithDataProviderStub::class, 'parked']);

        Assert::same($result->status, Status::Skipped);
        Assert::null($result->getAttribute(MultipleResult::class));
        Assert::same(SkipWithDataProviderStub::$providerCalls, 0);
    }

    public function retryDoesNotEngageForParkedTest(): void
    {
        $attempts = SkipWithRetryStub::$attempts;

        $result = TestRunner::runTest([SkipWithRetryStub::class, 'parked']);

        Assert::same($result->status, Status::Skipped);
        Assert::same(SkipWithRetryStub::$attempts - $attempts, 0);
    }

    public function repeatDoesNotEngageForParkedTest(): void
    {
        $result = TestRunner::runTest([SkipWithRepeatStub::class, 'parked']);

        Assert::same($result->status, Status::Skipped);
        Assert::false(SkipWithRepeatStub::$bodyRan);
    }

    /**
     * The common ground of the hook/provider/retry/repeat checks above: a parked test never
     * enters the per-test pipeline at all. A spy interceptor on that pipeline sees the
     * enabled neighbors of the catalog and none of the parked tests.
     */
    public function parkedTestsNeverEnterThePerTestPipeline(): void
    {
        $offset = \count(PipelineEntrySpyPlugin::$entered);

        TestRunner::runTest([SkipMethodStub::class, 'parked']);

        $entered = \array_slice(PipelineEntrySpyPlugin::$entered, $offset);
        Assert::contains($entered, SkipMethodStub::class . '::enabled');
        Assert::same(\array_intersect($entered, [
            SkipMethodStub::class . '::parked',
            SkipMethodStub::class . '::parkedNoReason',
            SkipWithHooksStub::class . '::parked',
            SkipWithDataProviderStub::class . '::parked',
            SkipWithRetryStub::class . '::parked',
            SkipWithRepeatStub::class . '::parked',
            SkipInFiberStub::class . '::parked',
            'Tests\Test\Stub\Skip\parked_function',
        ]), []);
    }

    /**
     * Fiber compatibility: the skip interceptor wraps the fiber batch runner instead of
     * replacing it. The round-robin interleaving of the two enabled tests is produced only by
     * the case scheduler — run sequentially, their `\Fiber::suspend()` would throw and the
     * log would stop short — while the parked test is still skipped.
     */
    public function fiberBatchRunnerSurvivesTheWrap(): void
    {
        $offset = \count(SkipInFiberStub::$log);

        $parked = TestRunner::runTest([SkipInFiberStub::class, 'parked']);

        Assert::same($parked->status, Status::Skipped);
        Assert::same(
            \array_slice(SkipInFiberStub::$log, $offset),
            ['first.1', 'second.1', 'first.2', 'second.2'],
        );
    }
}
