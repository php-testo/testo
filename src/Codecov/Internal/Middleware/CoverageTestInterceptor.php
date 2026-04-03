<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal\Middleware;

use Testo\Codecov\CoverageDriver;
use Testo\Codecov\Dto\CoverageResult;
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
        $this->driver->start();

        try {
            $result = $next($info);
        } finally {
            $coverage = $this->driver->collect();
        }

        return $result->withAttribute(CoverageResult::class, $coverage);
    }
}
