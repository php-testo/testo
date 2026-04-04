<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal\Middleware;

use Testo\Codecov\CoversNothing;
use Testo\Codecov\Internal\CoverageDriver;
use Testo\Common\Reflection;
use Testo\Codecov\Result\CoverageResult;
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
 *
 * @internal
 */
#[InterceptorOptions(order: \PHP_INT_MAX)]
final readonly class CoverageTestInterceptor implements TestRunInterceptor
{
    public function __construct(
        private CoverageDriver $driver,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        if (self::hasCoversNothing($info)) {
            return $next($info);
        }

        $this->driver->start();

        try {
            $result = $next($info);
        } finally {
            $coverage = $this->driver->collect();
        }

        return $result->withAttribute(CoverageResult::class, $coverage);
    }

    private static function hasCoversNothing(TestInfo $info): bool
    {
        if (Reflection::fetchFunctionAttributes(
            $info->testDefinition->reflection,
            attributeClass: CoversNothing::class,
            limit: 1,
        ) !== []) {
            return true;
        }

        $class = $info->caseInfo->definition->reflection;

        return $class !== null
            && Reflection::fetchClassAttributes($class, attributeClass: CoversNothing::class) !== [];
    }
}
