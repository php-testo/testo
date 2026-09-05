<?php

declare(strict_types=1);

namespace Testo\Test\Internal;

use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Common\Reflection;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Exception\SkipTest;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Core\Value\TestType;
use Testo\Event\Test\TestPipelineFinished;
use Testo\Event\Test\TestPipelineStarting;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestCaseRunInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;
use Testo\Test\Skip;
use Testo\Test\TestPlugin;

/**
 * Reports {@see Skip}-marked tests as skipped without running them.
 *
 * A case-level interceptor (registered by {@see TestPlugin}, also the attribute's fallback):
 * it removes every parked test from the case's test set before handing the case on — so
 * lifecycle hooks and the per-test pipeline never see them — and appends a synthetic
 * {@see Status::Skipped} result for each through the case's batch runner
 * ({@see CaseInfo::withBatchRunner}). A runner already installed by an outer interceptor
 * (e.g. testo/fiber's) is wrapped, never replaced. Every synthetic result is announced with
 * the {@see TestPipelineStarting}/{@see TestPipelineFinished} pair, so reporters render the
 * skipped lines inside the case block; the core aggregates the case as usual.
 *
 * Ordering: {@see InterceptorOptions::ORDER_DEFAULT} sits outer to the lifecycle interceptor
 * (so filtering happens before `#[BeforeClass]`) and inner to the fiber interceptor (so a
 * fiber batch runner is already on the case and gets wrapped).
 *
 * Never throws for a parked test — a throw from a case interceptor aborts the whole case.
 *
 * @internal
 * @psalm-internal Testo\Test
 */
#[InterceptorOptions(
    order: InterceptorOptions::ORDER_DEFAULT,
    # A class-level #[Skip] spawns a second instance through the fallback alias, next to the
    # one registered by TestPlugin; First collapses the duplicate onto the registered one.
    onConflict: ConflictPolicy::First,
    testType: TestType::Test,
)]
final readonly class SkipInterceptor implements TestCaseRunInterceptor
{
    /**
     * Takes no {@see Skip} parameter on purpose: the container also builds the instance for
     * the {@see TestPlugin} registration, where no attribute is at hand. The attributes are
     * looked up per case in {@see self::findParked()} instead.
     */
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[\Override]
    public function runTestCase(CaseInfo $info, callable $next): CaseResult
    {
        $parked = $this->findParked($info);

        if ($parked === []) {
            return $next($info);
        }

        foreach ($parked as $name => $_) {
            $info->definition->tests->undefine($name);
        }

        # The case still runs (class-level hooks, events, the remaining tests): the parked
        # results are appended by the batch runner inside the case window.
        $inner = $info->batchRunner;
        return $next($info->withBatchRunner(
            function (array $handlers) use ($inner, $info, $parked): array {
                $results = $inner === null
                    ? \array_map(static fn(callable $handler): TestResult => $handler(), $handlers)
                    : $inner($handlers);

                foreach ($parked as $name => [$definition, $attribute]) {
                    $results[] = $this->reportSkipped($info, $name, $definition, $attribute);
                }

                return $results;
            },
        ));
    }

    /**
     * Collects the parked tests of the case: a method/function-level `#[Skip]` wins over the
     * class-level one; the class-level attribute is inherited from parents and traits.
     *
     * @return array<non-empty-string, array{TestDefinition, Skip}>
     */
    private function findParked(CaseInfo $info): array
    {
        $classAttribute = null;
        $reflection = $info->definition->reflection;
        if ($reflection !== null) {
            $attributes = Reflection::fetchClassAttributes($reflection, attributeClass: Skip::class, limit: 1);
            $attributes === [] or $classAttribute = $attributes[0]->newInstance();
        }

        $parked = [];
        foreach ($info->definition->tests->getTests() as $name => $definition) {
            $attributes = Reflection::fetchFunctionAttributes(
                $definition->reflection,
                attributeClass: Skip::class,
                limit: 1,
            );
            $attribute = $attributes === [] ? $classAttribute : $attributes[0]->newInstance();

            $attribute === null or $parked[$name] = [$definition, $attribute];
        }

        return $parked;
    }

    /**
     * Builds the synthetic result for a parked test and dispatches its pipeline events, so
     * reporters that render test lines from those events see the test as any other.
     */
    private function reportSkipped(
        CaseInfo $case,
        string $name,
        TestDefinition $definition,
        Skip $attribute,
    ): TestResult {
        $testInfo = (new TestInfo(name: $name, caseInfo: $case, testDefinition: $definition))
            ->withAttributes([Skip::class => [$attribute]]);

        $this->eventDispatcher->dispatch(new TestPipelineStarting($testInfo));

        $result = new TestResult(
            info: $testInfo,
            status: Status::Skipped,
            failure: new SkipTest(self::reason($testInfo, $attribute)),
            attributes: ['duration' => 0, 'description' => $definition->getDescription()],
            summary: Summary::forTest(Status::Skipped),
        );

        $this->eventDispatcher->dispatch(new TestPipelineFinished($testInfo, $result));

        return $result;
    }

    /**
     * `{testId} is skipped via #[Skip]`, extended with ` ==> {reason}` when a reason is given.
     * The generated part is always present, so reporters that render skip failure messages
     * can show that the skip came from #[Skip];
     * the test id is the test's address ({@see \Testo\Core\Context\Identity\TestIdentity::fqn()}),
     * the string `--filter` takes back.
     */
    private static function reason(TestInfo $info, Skip $attribute): string
    {
        $message = "{$info->identity->fqn()} is skipped via #[Skip]";

        return $attribute->reason === '' ? $message : "{$message} ==> {$attribute->reason}";
    }
}
