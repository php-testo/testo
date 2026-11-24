<?php

declare(strict_types=1);

namespace Tests\Skip\Feature;

use Testo\Application\Exception\PipelineFailure;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Value\Status;
use Testo\Filter\Group;
use Testo\Requires;
use Testo\Skip\Internal\SkipDecision;
use Testo\Skip\Internal\SkipInterceptor;
use Testo\Skip\Internal\SkipLocatorInterceptor;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Tests\Skip\Stub\PipelineEntrySpyPlugin;
use Tests\Skip\Stub\Skip\RequiresClassLevelStub;
use Tests\Skip\Stub\Skip\RequiresStub;
use Tests\Skip\Stub\Skip\RequiresThrowingClassStub;
use Tests\Skip\Stub\Skip\RequiresUnmetClassStub;

/**
 * End-to-end checks that a test with an unmet {@see Requires} is reported as {@see Status::Skipped}
 * the way a `#[Skip]`-marked one is, and runs when every requirement is met.
 *
 * Replays the same `Stub/Skip` directory as {@see SkipFeatureTest}, with the same consequences: the
 * stubs' static state survives the runs, and the fiber stub there puts this class in the `async`
 * group.
 */
#[Test]
#[Group('async')]
#[TestingSuite(path: __DIR__ . '/../Stub/Skip', plugins: [PipelineEntrySpyPlugin::class])]
#[Covers(Requires::class)]
#[Covers(SkipDecision::class)]
#[Covers(SkipInterceptor::class)]
#[Covers(SkipLocatorInterceptor::class)]
final class RequiresFeatureTest
{
    public function __construct()
    {
        require_once __DIR__ . '/../Stub/Skip/skip_functions.php';
    }

    public function unmetRequirementSkipsWithComposedMessage(): void
    {
        $result = TestRunner::runTest([RequiresStub::class, 'skippedByArrayCondition']);

        Assert::same($result->status, Status::Skipped);
        Assert::same(
            $result->failure?->getMessage(),
            RequiresStub::class . '::skippedByArrayCondition is skipped: requirement '
            . RequiresStub::class . '::unmet() is not met ==> needs the unmet thing',
        );
    }

    public function acceptsStaticMethodStringAndFunctionName(): void
    {
        $method = TestRunner::runTest([RequiresStub::class, 'skippedByStringMethodCondition']);
        $function = TestRunner::runTest([RequiresStub::class, 'skippedByFunctionCondition']);

        Assert::same($method->status, Status::Skipped);
        Assert::same(
            $method->failure?->getMessage(),
            RequiresStub::class . '::skippedByStringMethodCondition is skipped: requirement '
            . RequiresStub::class . '::unmet() is not met',
        );
        Assert::same($function->status, Status::Skipped);
        Assert::same(
            $function->failure?->getMessage(),
            RequiresStub::class . '::skippedByFunctionCondition is skipped: requirement '
            . 'Tests\Skip\Stub\Skip\unmetRequirement() is not met',
        );
    }

    /**
     * A bare name and `self::` resolve against the test class, as `#[DataProvider]` names do.
     */
    public function bareNameAndSelfResolveToTheTestClass(): void
    {
        $bare = TestRunner::runTest([RequiresStub::class, 'skippedByBareMethodName']);
        $self = TestRunner::runTest([RequiresStub::class, 'skippedBySelfMethodString']);

        Assert::same($bare->status, Status::Skipped);
        Assert::same(
            $bare->failure?->getMessage(),
            RequiresStub::class . '::skippedByBareMethodName is skipped: requirement '
            . RequiresStub::class . '::unmet() is not met',
        );
        Assert::same($self->status, Status::Skipped);
    }

    public function runsWhenEveryRequirementIsMet(): void
    {
        $result = TestRunner::runTest([RequiresStub::class, 'runsWhenEveryRequirementIsMet']);

        Assert::same($result->status, Status::Passed);
    }

    public function oneUnmetRequirementOfSeveralSkips(): void
    {
        $result = TestRunner::runTest([RequiresStub::class, 'skippedWhenOneOfSeveralIsUnmet']);

        Assert::same($result->status, Status::Skipped);
    }

    /**
     * Both the locator and the interceptor ask, yet the requirement is checked once per run.
     */
    public function requirementIsCheckedOncePerRun(): void
    {
        $calls = RequiresStub::$countedCalls;

        $result = TestRunner::runTest([RequiresStub::class, 'requirementCheckedOnce']);

        Assert::same($result->status, Status::Skipped);
        Assert::same(RequiresStub::$countedCalls - $calls, 1);
    }

    public function throwingRequirementAbortsOnlyItsTest(): void
    {
        $result = TestRunner::runTest([RequiresStub::class, 'throwingRequirement']);
        $neighbor = TestRunner::runTest([RequiresStub::class, 'runsWhenEveryRequirementIsMet']);

        Assert::same($result->status, Status::Aborted);
        Assert::instanceOf($result->failure, PipelineFailure::class);
        Assert::same($result->failure?->getPrevious()?->getMessage(), 'broken requirement');
        Assert::same($neighbor->status, Status::Passed);
    }

    public function nonBoolRequirementAbortsTheTest(): void
    {
        $result = TestRunner::runTest([RequiresStub::class, 'nonBoolRequirement']);

        Assert::same($result->status, Status::Aborted);
        Assert::same(
            $result->failure?->getPrevious()?->getMessage(),
            'Requirement php_sapi_name() must return bool, string returned.',
        );
    }

    /**
     * The case has three tests under the class-level requirement, yet it is checked once per run.
     */
    public function classRequirementIsCheckedOncePerCase(): void
    {
        $checks = RequiresClassLevelStub::$classChecks;

        TestRunner::runTest([RequiresClassLevelStub::class, 'runsUnderMetClassRequirement']);

        Assert::same(RequiresClassLevelStub::$classChecks - $checks, 1);
    }

    /**
     * The kept throw spares the probe: once per case, for both the locator and every pipeline.
     */
    public function throwingClassRequirementIsCalledOnceAndAbortsEveryTest(): void
    {
        $calls = RequiresThrowingClassStub::$calls;

        $first = TestRunner::runTest([RequiresThrowingClassStub::class, 'first']);

        Assert::same($first->status, Status::Aborted);
        Assert::same($first->failure?->getPrevious()?->getMessage(), 'probe timed out');
        Assert::same(RequiresThrowingClassStub::$calls - $calls, 1);
        Assert::same(RequiresThrowingClassStub::$beforeClassCalls, 0);
    }

    public function malformedArrayConditionAbortsWithAClearMessage(): void
    {
        $result = TestRunner::runTest([RequiresStub::class, 'malformedArrayCondition']);

        Assert::same($result->status, Status::Aborted);
        Assert::same(
            $result->failure?->getPrevious()?->getMessage(),
            '#[Requires] condition array must be [class, method], got ["' . \addslashes(RequiresStub::class) . '"].',
        );
    }

    public function metClassRequirementAddsUpWithMethodOnes(): void
    {
        $runs = TestRunner::runTest([RequiresClassLevelStub::class, 'runsUnderMetClassRequirement']);
        $skipped = TestRunner::runTest([RequiresClassLevelStub::class, 'skippedByOwnRequirement']);

        Assert::same($runs->status, Status::Passed);
        Assert::same($skipped->status, Status::Skipped);
        Assert::true(\str_ends_with((string) $skipped->failure?->getMessage(), ' ==> method requirement'));
    }

    public function skipWinsWithoutCheckingRequirements(): void
    {
        $result = TestRunner::runTest([RequiresClassLevelStub::class, 'skipWinsOverRequirement']);

        Assert::same($result->status, Status::Skipped);
        Assert::same(
            $result->failure?->getMessage(),
            RequiresClassLevelStub::class . '::skipWinsOverRequirement is skipped via #[Skip] ==> parked',
        );
    }

    public function unmetClassRequirementSkipsTheWholeCaseUnbuilt(): void
    {
        $first = TestRunner::runTest([RequiresUnmetClassStub::class, 'first']);
        $second = TestRunner::runTest([RequiresUnmetClassStub::class, 'second']);

        Assert::same($first->status, Status::Skipped);
        Assert::same($second->status, Status::Skipped);
        Assert::true(\str_ends_with((string) $second->failure?->getMessage(), ' ==> class requirement'));
        Assert::false(RequiresUnmetClassStub::$constructed);
    }

    public function unmetRequirementKeepsTheTestOutOfItsPipeline(): void
    {
        $offset = \count(PipelineEntrySpyPlugin::$entered);

        TestRunner::runTest([RequiresStub::class, 'skippedByArrayCondition']);

        Assert::array(\array_slice(PipelineEntrySpyPlugin::$entered, $offset))
            ->contains(RequiresStub::class . '::runsWhenEveryRequirementIsMet')
            ->notContains(RequiresStub::class . '::skippedByArrayCondition');
    }
}
