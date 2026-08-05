<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal\Middleware;

use Testo\Codecov\Config\CoverageLevel;
use Testo\Codecov\Covers;
use Testo\Codecov\CoversNothing;
use Testo\Codecov\Internal\CoverageAttribute;
use Testo\Codecov\Internal\CoverageDriver;
use Testo\Codecov\Internal\CoverageFilter;
use Testo\Codecov\Result\CoverageResult;
use Testo\Common\Reflection;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;

/**
 * Collects per-test code coverage data.
 *
 * Wraps each test execution with driver start/collect calls and attaches
 * the coverage result to the {@see TestResult} attributes.
 *
 * Tests marked with {@see CoversNothing} are executed without coverage collection.
 * Tests marked with {@see Covers} have their coverage filtered to the specified targets.
 *
 * A test's window stays bound to it across fiber suspensions. The driver collects process-wide and its
 * window cannot nest — a `collect()` from any fiber ends collection for all of them — so a test running
 * in a fiber is driven through a trampoline: on every suspension the window is closed and what it holds
 * is banked, on resumption a fresh one is opened. Lines a sibling test executes while this one is parked
 * therefore never land here, and the banked slices add up to exactly this test's coverage.
 *
 * Keeping no window open across a switch is also what keeps the process alive: with
 * `XDEBUG_CC_BRANCH_CHECK` (any {@see CoverageLevel} above {@see CoverageLevel::Line}) a window that
 * spans a fiber switch corrupts memory inside XDebug and kills the run outright.
 *
 * The trampoline is why this sits at {@see InterceptorOptions::ORDER_COVERAGE}, outer to
 * {@see InterceptorOptions::ORDER_ASYNC_COROUTINE} rather than innermost: an interceptor there hands the
 * test to a fiber it owns and resumes directly — `testo/bridge-revolt` dispatches the body onto the
 * Revolt event loop — and a suspension relayed out of such a fiber is never resumed. From here the
 * trampoline only ever relays through fibers Testo drives, and a coroutine-dispatched test is measured
 * in one window, which is enough because the loop runs one test at a time.
 *
 * @internal
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_COVERAGE)]
final readonly class CoverageTestInterceptor implements TestRunInterceptor
{
    /**
     * @param non-empty-list<non-empty-string> $testTypes Test types to collect coverage for. Empty = all.
     */
    public function __construct(
        private CoverageDriver $driver,
        private array $testTypes = [],
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        if ($this->testTypes !== [] && !\in_array($info->caseInfo->definition->type, $this->testTypes, true)) {
            return $next($info);
        }

        $attributes = self::getCoverageAttributes($info);

        $hasCoversNothing = false;
        $hasCovers = false;
        foreach ($attributes as $attr) {
            $attr::class === CoversNothing::class and $hasCoversNothing = true;
            $attr::class === Covers::class and $hasCovers = true;
        }

        $hasCoversNothing && $hasCovers and throw new \LogicException(\sprintf(
            'Test "%s" has both #[Covers] and #[CoversNothing] on the same level. Remove one of them.',
            $info->name,
        ));

        if ($hasCoversNothing) {
            return $next($info);
        }

        $banked = new CoverageResult();

        $this->driver->start();

        try {
            if (\Fiber::getCurrent() === null) {
                $result = $next($info);
            } else {
                # Trampoline the test so its window can be closed around every suspension it relays —
                # inlined rather than delegated to keep the test's own stack as shallow as it would be
                # without coverage.
                $fiber = new \Fiber(static fn(): TestResult => $next($info));
                $value = $fiber->start();
                while (!$fiber->isTerminated()) {
                    # Leaving our slice: bank it and close the window before anyone else gets to run.
                    $banked = $banked->merge($this->driver->collect());
                    try {
                        $resume = \Fiber::suspend($value);
                    } catch (\Throwable $e) {
                        $this->driver->start();
                        $value = $fiber->throw($e);
                        continue;
                    }

                    $this->driver->start();
                    $value = $fiber->resume($resume);
                }

                /** @var TestResult $result */
                $result = $fiber->getReturn();
            }
        } finally {
            # Every exit path leaves the window open, so it holds this test's last slice.
            $coverage = $banked->merge($this->driver->collect());
        }

        /**
         * Filter by #[Covers] targets
         * @var list<Covers> $coversTargets
         */
        $coversTargets = \array_filter($attributes, static fn(CoverageAttribute $a): bool => $a instanceof Covers);
        $coversTargets === [] or $coverage = CoverageFilter::apply($coverage, \array_values($coversTargets));

        # The address names the concrete case class, not the method's declaring class, so a `#[Test]`
        # inherited from an abstract base is attributed to the subclass — which is what keeps
        # `<covered by="Concrete::method">` aligned with the JUnit `<testsuite name="Concrete">` that
        # Infection joins on. Data sets deliberately share one identifier: `qualifiedName()` carries no
        # coordinates, and per-data-set granularity would break that join rather than sharpen it.
        $coverage = $coverage->withTestMethod($info->identity->qualifiedName());

        return $result->withAttribute(CoverageResult::class, $coverage);
    }

    /**
     * Collects all coverage attributes from the method/function and class hierarchy.
     *
     * @return list<CoverageAttribute>
     */
    private static function getCoverageAttributes(TestInfo $info): array
    {
        # MERGE_FIRST: closest layer wins — child's attributes override parent's
        $attributes = \array_map(
            static fn(\ReflectionAttribute $a): CoverageAttribute => $a->newInstance(),
            Reflection::fetchFunctionAttributes(
                $info->testDefinition->reflection,
                attributeClass: CoverageAttribute::class,
                flags: \ReflectionAttribute::IS_INSTANCEOF,
                mergePolicy: Reflection::MERGE_FIRST,
            ),
        );

        # Method-level attributes take priority over class-level
        if ($attributes === []) {
            $class = $info->caseInfo->definition->reflection;
            $class === null or $attributes = \array_map(
                static fn(\ReflectionAttribute $a): CoverageAttribute => $a->newInstance(),
                Reflection::fetchClassAttributes(
                    $class,
                    attributeClass: CoverageAttribute::class,
                    flags: \ReflectionAttribute::IS_INSTANCEOF,
                    mergePolicy: Reflection::MERGE_FIRST,
                ),
            );
        }

        return $attributes;
    }
}
