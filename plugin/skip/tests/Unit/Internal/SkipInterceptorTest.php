<?php

declare(strict_types=1);

namespace Tests\Skip\Unit\Internal;

use Internal\Path;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Definition\TestDefinitions;
use Testo\Core\Exception\SkipTest;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Core\Value\TestType;
use Testo\Event\Test\TestPipelineFinished;
use Testo\Event\Test\TestPipelineStarting;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Policy\ConflictPolicy;
use Testo\Test;
use Testo\Skip\Internal\SkipInterceptor;
use Testo\Skip;
use Tests\Skip\Unit\Fixture\SkipClassLevelFixture;
use Tests\Skip\Unit\Fixture\SkipMixedMethodsFixture;

/**
 * @see SkipInterceptor
 */
#[Test]
#[Covers(SkipInterceptor::class)]
final class SkipInterceptorTest
{
    /**
     * By the time `$next` (and with it every inner interceptor and lifecycle hook) runs,
     * the skipped tests are no longer in the case's active test set.
     */
    public function filtersSkippedTestsBeforeNext(): void
    {
        $interceptor = new SkipInterceptor(self::createDispatcher());
        $info = self::createCaseInfo(SkipMixedMethodsFixture::class, 'skipped', 'skippedNoReason', 'enabled');
        $seenTests = null;

        $interceptor->runTestCase($info, self::coreNext($seenTests));

        Assert::same($seenTests, ['enabled']);
    }

    /**
     * The skipped tests still come back in the case result — as synthetic Skipped results
     * with a SkipTest failure and their own `Summary::forTest(Status::Skipped)`, since no core
     * runner produces one for them.
     */
    public function returnsSyntheticSkippedResults(): void
    {
        $interceptor = new SkipInterceptor(self::createDispatcher());
        $info = self::createCaseInfo(SkipMixedMethodsFixture::class, 'skipped', 'enabled');

        $result = $interceptor->runTestCase($info, self::coreNext());

        $skipped = self::findResult($result, 'skipped');
        Assert::same($skipped->status, Status::Skipped);
        Assert::instanceOf($skipped->failure, SkipTest::class);
        Assert::same($skipped->summary->count(Status::Skipped), 1);
        Assert::same($result->summary->count(Status::Skipped), 1);
        Assert::same($result->summary->count(Status::Passed), 1);
    }

    public function composesReasonAfterGeneratedPart(): void
    {
        $interceptor = new SkipInterceptor(self::createDispatcher());
        $info = self::createCaseInfo(SkipMixedMethodsFixture::class, 'skipped');

        $result = $interceptor->runTestCase($info, self::coreNext());

        Assert::same(
            self::findResult($result, 'skipped')->failure?->getMessage(),
            SkipMixedMethodsFixture::class
            . '::skipped is skipped via #[Skip] ==> broken by the pricing rework, see ISSUE-123',
        );
    }

    /**
     * An empty reason falls back to the generated part alone — no reporter ever shows an
     * empty skip message.
     */
    public function fallsBackToGeneratedMessageWithoutReason(): void
    {
        $interceptor = new SkipInterceptor(self::createDispatcher());
        $info = self::createCaseInfo(SkipMixedMethodsFixture::class, 'skippedNoReason');

        $result = $interceptor->runTestCase($info, self::coreNext());

        Assert::same(
            self::findResult($result, 'skippedNoReason')->failure?->getMessage(),
            SkipMixedMethodsFixture::class . '::skippedNoReason is skipped via #[Skip]',
        );
    }

    /**
     * The origin contract: a result skipped by `#[Skip]` carries the attribute instances in its
     * info, so downstream consumers can tell a declarative skip from a runtime one.
     */
    public function stampsOriginAttributeOnSyntheticInfo(): void
    {
        $interceptor = new SkipInterceptor(self::createDispatcher());
        $info = self::createCaseInfo(SkipMixedMethodsFixture::class, 'skipped', 'enabled');

        $result = $interceptor->runTestCase($info, self::coreNext());

        $origin = self::findResult($result, 'skipped')->info->getAttribute(Skip::class);
        Assert::array($origin)->hasCount(1);
        Assert::instanceOf($origin[0], Skip::class);
        Assert::same($origin[0]->reason, 'broken by the pricing rework, see ISSUE-123');
        Assert::null(self::findResult($result, 'enabled')->info->getAttribute(Skip::class));
    }

    public function classLevelSkipSkipsEveryTest(): void
    {
        $interceptor = new SkipInterceptor(self::createDispatcher());
        $info = self::createCaseInfo(SkipClassLevelFixture::class, 'first', 'second');
        $seenTests = null;

        $result = $interceptor->runTestCase($info, self::coreNext($seenTests));

        Assert::same($seenTests, []);
        Assert::same(self::findResult($result, 'first')->status, Status::Skipped);
        Assert::same(self::findResult($result, 'second')->status, Status::Skipped);
    }

    /**
     * The method-level attribute wins as a whole: an empty method reason is not filled in
     * from the class reason.
     */
    public function methodReasonWinsOverClassReason(): void
    {
        $interceptor = new SkipInterceptor(self::createDispatcher());
        $info = self::createCaseInfo(SkipClassLevelFixture::class, 'first', 'second', 'third');

        $result = $interceptor->runTestCase($info, self::coreNext());

        Assert::true(\str_ends_with(
            (string) self::findResult($result, 'first')->failure?->getMessage(),
            ' ==> entire case is skipped',
        ));
        Assert::true(\str_ends_with(
            (string) self::findResult($result, 'second')->failure?->getMessage(),
            ' ==> method beats class',
        ));
        Assert::same(
            self::findResult($result, 'third')->failure?->getMessage(),
            SkipClassLevelFixture::class . '::third is skipped via #[Skip]',
        );
    }

    /**
     * A case with no skipped tests passes through untouched: same test set, no batch runner
     * installed.
     */
    public function passesThroughCaseWithoutSkippedTests(): void
    {
        $interceptor = new SkipInterceptor(self::createDispatcher());
        $info = self::createCaseInfo(SkipMixedMethodsFixture::class, 'enabled');
        $batchRunner = false;
        $seenTests = null;

        $interceptor->runTestCase($info, static function (CaseInfo $inner) use (&$batchRunner, &$seenTests): CaseResult {
            $batchRunner = $inner->batchRunner;
            $seenTests = \array_keys($inner->definition->tests->getTests());
            return new CaseResult(results: [], status: Status::Passed);
        });

        Assert::same($seenTests, ['enabled']);
        Assert::null($batchRunner);
    }

    /**
     * A batch runner already installed by an outer interceptor (e.g. testo/fiber's) keeps
     * driving the remaining tests — the wrapper wraps it instead of replacing it.
     */
    public function wrapsExistingBatchRunnerInsteadOfReplacing(): void
    {
        $interceptor = new SkipInterceptor(self::createDispatcher());
        $innerRunnerCalls = 0;
        $info = self::createCaseInfo(SkipMixedMethodsFixture::class, 'skipped', 'enabled')
            ->withBatchRunner(static function (array $handlers) use (&$innerRunnerCalls): array {
                ++$innerRunnerCalls;
                return \array_map(static fn(callable $handler): TestResult => $handler(), $handlers);
            });

        $result = $interceptor->runTestCase($info, self::coreNext());

        Assert::same($innerRunnerCalls, 1);
        Assert::same(self::findResult($result, 'enabled')->status, Status::Passed);
        Assert::same(self::findResult($result, 'skipped')->status, Status::Skipped);
    }

    /**
     * Reporters render test lines from the pipeline events: Starting before Finished, both
     * carrying the same address, so a reporter keyed on the identity closes what it opened.
     */
    public function dispatchesPipelineEventsForSkippedTests(): void
    {
        $dispatcher = self::createDispatcher();
        $interceptor = new SkipInterceptor($dispatcher);
        $info = self::createCaseInfo(SkipMixedMethodsFixture::class, 'skipped');

        $result = $interceptor->runTestCase($info, self::coreNext());

        $events = $dispatcher->dispatched;
        Assert::count($events, 2);
        [$starting, $finished] = $events;
        Assert::instanceOf($starting, TestPipelineStarting::class);
        Assert::instanceOf($finished, TestPipelineFinished::class);
        Assert::same($starting->testInfo->name, 'skipped');
        Assert::same($finished->testInfo->identity, $starting->testInfo->identity);
        Assert::same($finished->testResult, self::findResult($result, 'skipped'));
    }

    /**
     * The terminal renders a test's PHPDoc description from the result attributes (as the
     * regular test path stamps it), so the synthetic result must carry it too.
     */
    public function carriesDescriptionInSyntheticResult(): void
    {
        $interceptor = new SkipInterceptor(self::createDispatcher());
        $info = self::createCaseInfo(SkipMixedMethodsFixture::class, 'skipped');

        $result = $interceptor->runTestCase($info, self::coreNext());

        Assert::same(
            self::findResult($result, 'skipped')->attributes['description'],
            'Checks that order totals include the reworked pricing.',
        );
    }

    /**
     * A skipped test is deactivated, not discarded: it leaves the case's active test set — the
     * only set the core runs — yet stays a member of the case for anything that reads them all.
     */
    public function skippedTestIsDeactivatedNotDiscarded(): void
    {
        $interceptor = new SkipInterceptor(self::createDispatcher());
        $info = self::createCaseInfo(SkipMixedMethodsFixture::class, 'skipped', 'enabled');

        $interceptor->runTestCase($info, self::coreNext());

        $tests = $info->definition->tests;
        Assert::array($tests->getTests())->hasKeys('enabled')->doesNotHaveKeys('skipped');
        Assert::array($tests->getTests(active: false))->hasKeys('skipped');
        Assert::array($tests->all())->hasKeys('skipped', 'enabled');
    }

    /**
     * `#[Skip]` on a non-test member is inert. A case is prefilled with every member of the class
     * — helpers and lifecycle hooks included — and the interceptor walks its tests only, so an
     * attribute on a non-test has nothing to act on.
     */
    public function skipOnANonTestMemberIsInert(): void
    {
        $interceptor = new SkipInterceptor(self::createDispatcher());
        $info = self::createCaseInfoWith(SkipMixedMethodsFixture::class, [
            'skipped' => new TestDefinition(
                new \ReflectionMethod(SkipMixedMethodsFixture::class, 'skipped'),
                isTest: false,
            ),
            'enabled' => new TestDefinition(new \ReflectionMethod(SkipMixedMethodsFixture::class, 'enabled')),
        ]);

        $result = $interceptor->runTestCase($info, self::coreNext());

        Assert::same($result->summary->count(Status::Skipped), 0);
        Assert::same($result->summary->count(Status::Passed), 1);
        Assert::same(self::findResult($result, 'enabled')->status, Status::Passed);
    }

    /**
     * A test an earlier filter already dropped is not resurrected as Skipped: `--filter`/`--group`
     * deactivate at location time, and reporting such a test would put it back into a run it was
     * excluded from.
     */
    public function alreadyFilteredTestIsNotReportedAsSkipped(): void
    {
        $interceptor = new SkipInterceptor(self::createDispatcher());
        $info = self::createCaseInfoWith(SkipMixedMethodsFixture::class, [
            'skipped' => new TestDefinition(
                new \ReflectionMethod(SkipMixedMethodsFixture::class, 'skipped'),
                active: false,
            ),
            'enabled' => new TestDefinition(new \ReflectionMethod(SkipMixedMethodsFixture::class, 'enabled')),
        ]);

        $result = $interceptor->runTestCase($info, self::coreNext());

        Assert::same($result->summary->count(Status::Skipped), 0);
        Assert::same($result->summary->count(Status::Passed), 1);
        Assert::same(self::findResult($result, 'enabled')->status, Status::Passed);
    }

    /**
     * `#[Skip]` is a plain-test feature: the interceptor declares `testType: TestType::Test`,
     * so on a bench or inline case the type filter drops it and the attribute is inert.
     */
    public function declaresTestTypeScopingSkipToPlainTests(): void
    {
        $attributes = (new \ReflectionClass(SkipInterceptor::class))
            ->getAttributes(InterceptorOptions::class);

        Assert::count($attributes, 1);
        Assert::same($attributes[0]->newInstance()->testType, TestType::Test);
    }

    /**
     * The rest of the placement contract: `ORDER_DEFAULT` is the slot the class docblock claims
     * (outer to the lifecycle interceptor, inner to the fiber one), and `ConflictPolicy::First`
     * is what collapses the instances the fallback alias spawns per `#[Skip]` occurrence.
     */
    public function declaresOrderAndConflictPolicy(): void
    {
        $attributes = (new \ReflectionClass(SkipInterceptor::class))
            ->getAttributes(InterceptorOptions::class);

        Assert::count($attributes, 1);
        # Both values equal the InterceptorOptions defaults, so pin that they are written out.
        Assert::array($attributes[0]->getArguments())->hasKeys('order', 'onConflict');
        $options = $attributes[0]->newInstance();
        Assert::same($options->order, InterceptorOptions::ORDER_DEFAULT);
        Assert::same($options->onConflict, ConflictPolicy::First);
    }

    private static function createDispatcher(): EventDispatcherInterface
    {
        return new class implements EventDispatcherInterface {
            /** @var list<object> */
            public array $dispatched = [];

            #[\Override]
            public function dispatch(object $event): object
            {
                $this->dispatched[] = $event;
                return $event;
            }
        };
    }

    /**
     * @param class-string $class
     * @param non-empty-string ...$methods
     */
    private static function createCaseInfo(string $class, string ...$methods): CaseInfo
    {
        $definitions = [];
        foreach ($methods as $method) {
            $definitions[$method] = new TestDefinition(new \ReflectionMethod($class, $method));
        }

        return self::createCaseInfoWith($class, $definitions);
    }

    /**
     * The same case built from definitions that carry their own flags — the shape a case has after
     * prefilling (non-test members) or after an earlier filter deactivated one of its tests.
     *
     * @param class-string $class
     * @param array<non-empty-string, TestDefinition> $definitions
     */
    private static function createCaseInfoWith(string $class, array $definitions): CaseInfo
    {
        $caseDefinition = new CaseDefinition(
            name: $class,
            type: 'test',
            file: Path::create(__FILE__),
            reflection: new \ReflectionClass($class),
            tests: TestDefinitions::fromArray(...$definitions),
        );

        return new CaseInfo(definition: $caseDefinition, suiteIdentity: new SuiteIdentity('Test/Unit'));
    }

    /**
     * A `$next` that mimics the core case loop: runs the surviving tests as Passed through
     * the case's batch runner (or inline without one) and aggregates the case summary.
     *
     * @param list<non-empty-string>|null $seenTests Filled with the test names that survived to `$next`.
     */
    private static function coreNext(?array &$seenTests = null): \Closure
    {
        return static function (CaseInfo $info) use (&$seenTests): CaseResult {
            $seenTests = \array_keys($info->definition->tests->getTests());

            $handlers = [];
            foreach ($info->definition->tests->getTests() as $name => $definition) {
                $handlers[] = static fn(): TestResult => new TestResult(
                    info: new TestInfo(name: $name, caseInfo: $info, testDefinition: $definition),
                    status: Status::Passed,
                    summary: Summary::forTest(Status::Passed),
                );
            }

            $runner = $info->batchRunner;
            /** @var list<TestResult> $results */
            $results = $runner === null
                ? \array_map(static fn(\Closure $handler): TestResult => $handler(), $handlers)
                : $runner($handlers);

            return new CaseResult(
                results: $results,
                status: Status::Passed,
                summary: Summary::combine(\array_map(static fn(TestResult $r): Summary => $r->summary, $results)),
            );
        };
    }

    private static function findResult(CaseResult $result, string $name): TestResult
    {
        foreach ($result as $testResult) {
            if ($testResult->info->name === $name) {
                return $testResult;
            }
        }

        throw new \LogicException("No result for test {$name}.");
    }
}
