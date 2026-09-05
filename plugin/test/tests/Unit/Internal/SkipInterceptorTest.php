<?php

declare(strict_types=1);

namespace Tests\Test\Unit\Internal;

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
use Testo\Test;
use Testo\Test\Internal\SkipInterceptor;
use Testo\Test\Skip;
use Tests\Test\Unit\Fixture\SkipClassLevelFixture;
use Tests\Test\Unit\Fixture\SkipMixedMethodsFixture;

/**
 * @see SkipInterceptor
 */
#[Test]
#[Covers(SkipInterceptor::class)]
final class SkipInterceptorTest
{
    /**
     * By the time `$next` (and with it every inner interceptor and lifecycle hook) runs,
     * the parked tests are no longer in the case's test set.
     */
    public function filtersParkedTestsBeforeNext(): void
    {
        $interceptor = new SkipInterceptor(self::createDispatcher());
        $info = self::createCaseInfo(SkipMixedMethodsFixture::class, 'parked', 'parkedNoReason', 'enabled');
        $seenTests = null;

        $interceptor->runTestCase($info, self::coreNext($seenTests));

        Assert::same($seenTests, ['enabled']);
    }

    /**
     * The parked tests still come back in the case result — as synthetic Skipped results
     * with a SkipTest failure and a self-stamped summary.
     */
    public function returnsSyntheticSkippedResults(): void
    {
        $interceptor = new SkipInterceptor(self::createDispatcher());
        $info = self::createCaseInfo(SkipMixedMethodsFixture::class, 'parked', 'enabled');

        $result = $interceptor->runTestCase($info, self::coreNext());

        $parked = self::findResult($result, 'parked');
        Assert::same($parked->status, Status::Skipped);
        Assert::instanceOf($parked->failure, SkipTest::class);
        Assert::same($parked->summary->count(Status::Skipped), 1);
        Assert::same($result->summary->count(Status::Skipped), 1);
        Assert::same($result->summary->count(Status::Passed), 1);
    }

    public function composesReasonAfterGeneratedPart(): void
    {
        $interceptor = new SkipInterceptor(self::createDispatcher());
        $info = self::createCaseInfo(SkipMixedMethodsFixture::class, 'parked');

        $result = $interceptor->runTestCase($info, self::coreNext());

        Assert::same(
            self::findResult($result, 'parked')->failure?->getMessage(),
            SkipMixedMethodsFixture::class
            . '::parked is skipped via #[Skip] ==> broken by the pricing rework, see ISSUE-123',
        );
    }

    /**
     * An empty reason falls back to the generated part alone — no reporter ever shows an
     * empty skip message.
     */
    public function fallsBackToGeneratedMessageWithoutReason(): void
    {
        $interceptor = new SkipInterceptor(self::createDispatcher());
        $info = self::createCaseInfo(SkipMixedMethodsFixture::class, 'parkedNoReason');

        $result = $interceptor->runTestCase($info, self::coreNext());

        Assert::same(
            self::findResult($result, 'parkedNoReason')->failure?->getMessage(),
            SkipMixedMethodsFixture::class . '::parkedNoReason is skipped via #[Skip]',
        );
    }

    /**
     * The origin contract: a `#[Skip]`-parked result carries the attribute instances in its
     * info, so downstream consumers can tell a declarative skip from a runtime one.
     */
    public function stampsOriginAttributeOnSyntheticInfo(): void
    {
        $interceptor = new SkipInterceptor(self::createDispatcher());
        $info = self::createCaseInfo(SkipMixedMethodsFixture::class, 'parked', 'enabled');

        $result = $interceptor->runTestCase($info, self::coreNext());

        $origin = self::findResult($result, 'parked')->info->getAttribute(Skip::class);
        Assert::true(\is_array($origin));
        Assert::count($origin, 1);
        Assert::instanceOf($origin[0], Skip::class);
        Assert::same($origin[0]->reason, 'broken by the pricing rework, see ISSUE-123');
        Assert::null(self::findResult($result, 'enabled')->info->getAttribute(Skip::class));
    }

    public function classLevelSkipParksEveryTest(): void
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
            ' ==> entire case is parked',
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
     * A case with no parked tests passes through untouched: same test set, no batch runner
     * installed.
     */
    public function passesThroughCaseWithoutParkedTests(): void
    {
        $interceptor = new SkipInterceptor(self::createDispatcher());
        $info = self::createCaseInfo(SkipMixedMethodsFixture::class, 'enabled');
        $batchRunner = false;

        $interceptor->runTestCase($info, static function (CaseInfo $inner) use (&$batchRunner): CaseResult {
            $batchRunner = $inner->batchRunner;
            return new CaseResult(results: [], status: Status::Passed);
        });

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
        $info = self::createCaseInfo(SkipMixedMethodsFixture::class, 'parked', 'enabled')
            ->withBatchRunner(static function (array $handlers) use (&$innerRunnerCalls): array {
                ++$innerRunnerCalls;
                return \array_map(static fn(callable $handler): TestResult => $handler(), $handlers);
            });

        $result = $interceptor->runTestCase($info, self::coreNext());

        Assert::same($innerRunnerCalls, 1);
        Assert::same(self::findResult($result, 'enabled')->status, Status::Passed);
        Assert::same(self::findResult($result, 'parked')->status, Status::Skipped);
    }

    /**
     * Reporters render test lines from the pipeline events: Starting before Finished, both
     * carrying the same address, so a reporter keyed on the identity closes what it opened.
     */
    public function dispatchesPipelineEventsForParkedTests(): void
    {
        $dispatcher = self::createDispatcher();
        $interceptor = new SkipInterceptor($dispatcher);
        $info = self::createCaseInfo(SkipMixedMethodsFixture::class, 'parked');

        $result = $interceptor->runTestCase($info, self::coreNext());

        /** @psalm-suppress UndefinedPropertyFetch The anonymous dispatcher exposes $dispatched. */
        $events = $dispatcher->dispatched;
        Assert::count($events, 2);
        [$starting, $finished] = $events;
        Assert::instanceOf($starting, TestPipelineStarting::class);
        Assert::instanceOf($finished, TestPipelineFinished::class);
        Assert::same($starting->testInfo->name, 'parked');
        Assert::same($finished->testInfo->identity, $starting->testInfo->identity);
        Assert::same($finished->testResult, self::findResult($result, 'parked'));
    }

    /**
     * The terminal renders a test's PHPDoc description from the result attributes (as the
     * regular test path stamps it), so the synthetic result must carry it too.
     */
    public function carriesDescriptionInSyntheticResult(): void
    {
        $interceptor = new SkipInterceptor(self::createDispatcher());
        $info = self::createCaseInfo(SkipMixedMethodsFixture::class, 'parked');

        $result = $interceptor->runTestCase($info, self::coreNext());

        Assert::same(
            self::findResult($result, 'parked')->attributes['description'],
            'Checks that order totals include the reworked pricing.',
        );
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
