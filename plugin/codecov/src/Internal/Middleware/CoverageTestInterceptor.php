<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal\Middleware;

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
 * @internal
 */
#[InterceptorOptions(order: \PHP_INT_MAX)]
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

        $this->driver->start();

        try {
            $result = $next($info);
        } finally {
            $coverage = $this->driver->collect();
        }

        /**
         * Filter by #[Covers] targets
         * @var list<Covers> $coversTargets
         */
        $coversTargets = \array_filter($attributes, static fn(CoverageAttribute $a): bool => $a instanceof Covers);
        $coversTargets === [] or $coverage = CoverageFilter::apply($coverage, \array_values($coversTargets));

        $method = self::buildMethodId($info);
        $method === null or $coverage = $coverage->withTestMethod($method);

        return $result->withAttribute(CoverageResult::class, $coverage);
    }

    /**
     * Builds a PHPUnit-style identifier for the test method.
     *
     * - Class methods: `Tests\\FooTest::testBar`.
     * - Free functions: `Tests\\testFooBar` (FQN, no leading backslash).
     *
     * For inherited tests the concrete (runtime) case class is used, NOT the
     * declaring class: a `#[Test]` method declared on an abstract base and run
     * through a subclass is attributed to the subclass. This keeps the coverage
     * `<covered by="Concrete::method">` aligned with the JUnit `<testsuite
     * name="Concrete">`, which Infection joins on by class name — `getDeclaringClass()`
     * would name the abstract base, which has no testsuite, and the lookup would fail.
     *
     * Data-set entries within data providers reuse the same identifier — Testo's
     * `--filter` selects by method, not by individual dataset, so per-dataset granularity
     * isn't useful for downstream consumers (e.g. Infection).
     *
     * @return non-empty-string|null
     */
    private static function buildMethodId(TestInfo $info): ?string
    {
        $reflection = $info->testDefinition->reflection;

        if ($reflection instanceof \ReflectionMethod) {
            $class = $info->caseInfo->definition->reflection;
            $className = $class?->getName() ?? $reflection->getDeclaringClass()->getName();
            $name = $className . '::' . $reflection->getName();
            return $name === '' ? null : $name;
        }

        $name = $reflection->getName();
        return $name === '' ? null : $name;
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
