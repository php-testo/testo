<?php

declare(strict_types=1);

namespace Testo\Skip\Internal;

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
use Testo\Skip;

/**
 * Reports {@see Skip}-marked tests as skipped without running them.
 *
 * Deactivates the marked tests before the case runs, so lifecycle hooks and the per-test pipeline
 * never see them, and appends a synthetic {@see Status::Skipped} result for each through the case's
 * batch runner. The skipped tests are handled at run time rather than at location, because a case
 * left without active tests is dropped by the suite factory together with its class-level hooks.
 *
 * Ordering: outer to the lifecycle interceptor, so the cut-off precedes `#[BeforeClass]`; inner to
 * the fiber interceptor, so a fiber batch runner is already on the case and gets wrapped.
 * `testType: TestType::Test` keeps `#[Bench]` and `#[TestInline]` cases out — a `#[Skip]` there is inert.
 *
 * Only the {@see TestPipelineStarting}/{@see TestPipelineFinished} pair is dispatched for a skipped
 * test: `TestStarting`/`TestFinished` announce a test body, and there is none. Installing a batch
 * runner also takes the case off the core's shallow-stack inline path.
 *
 * Never throws for a skipped test — a throw from a case interceptor aborts the whole case.
 *
 * @internal
 * @psalm-internal Testo\Skip
 */
#[InterceptorOptions(
    order: InterceptorOptions::ORDER_DEFAULT,
    # One instance is spawned per #[Skip] occurrence in the case; a single pass handles them all.
    onConflict: ConflictPolicy::First,
    testType: TestType::Test,
)]
final readonly class SkipInterceptor implements TestCaseRunInterceptor
{
    /**
     * Takes no {@see Skip} parameter: the instance is spawned by one of the case's `#[Skip]`
     * occurrences, yet has to handle every one of them.
     */
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[\Override]
    public function runTestCase(CaseInfo $info, callable $next): CaseResult
    {
        $skipped = $this->findSkipped($info);

        if ($skipped === []) {
            return $next($info);
        }

        # Deactivated, not discarded: the definitions are shared, and the synthetic results below
        # are the only delivery of these tests.
        foreach ($skipped as [$definition, $_]) {
            $definition->active = false;
        }

        $inner = $info->batchRunner;
        return $next($info->withBatchRunner(
            function (array $handlers) use ($inner, $info, $skipped): array {
                $results = $inner === null
                    ? \array_map(static fn(callable $handler): TestResult => $handler(), $handlers)
                    : $inner($handlers);

                foreach ($skipped as $name => [$definition, $attribute]) {
                    $results[] = $this->reportSkipped($info, $name, $definition, $attribute);
                }

                return $results;
            },
        ));
    }

    /**
     * `{testId} is skipped via #[Skip]`, extended with ` ==> {reason}` when a reason is given.
     * The test id is the string `--filter` takes back.
     */
    private static function reason(TestInfo $info, Skip $attribute): string
    {
        $message = "{$info->identity->fqn()} is skipped via #[Skip]";

        return $attribute->reason === '' ? $message : "{$message} ==> {$attribute->reason}";
    }

    /**
     * A method/function-level `#[Skip]` wins over the class-level one; the class-level attribute
     * is inherited from parents and traits.
     *
     * @return array<non-empty-string, array{TestDefinition, Skip}>
     */
    private function findSkipped(CaseInfo $info): array
    {
        $classAttribute = null;
        $reflection = $info->definition->reflection;
        if ($reflection !== null) {
            $attributes = Reflection::fetchClassAttributes($reflection, attributeClass: Skip::class, limit: 1);
            $attributes === [] or $classAttribute = $attributes[0]->newInstance();
        }

        $skipped = [];
        # Active tests only: a test deactivated by --filter/--group is not part of this run and
        # must not resurface as Skipped.
        foreach ($info->definition->tests->getTests() as $name => $definition) {
            $attributes = Reflection::fetchFunctionAttributes(
                $definition->reflection,
                attributeClass: Skip::class,
                limit: 1,
            );
            $attribute = $attributes === [] ? $classAttribute : $attributes[0]->newInstance();

            $attribute === null or $skipped[$name] = [$definition, $attribute];
        }

        return $skipped;
    }

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
}
