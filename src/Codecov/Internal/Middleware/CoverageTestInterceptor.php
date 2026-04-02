<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal\Middleware;

use Testo\Application\Config\FinderConfig;
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
 * the filtered coverage result to the {@see TestResult} attributes.
 *
 * @internal
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_RIGHT_BEFORE_TEST)]
final readonly class CoverageTestInterceptor implements TestRunInterceptor
{
    public function __construct(
        private CoverageDriver $driver,
        private FinderConfig $filter,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $this->driver->start();

        try {
            $result = $next($info);
        } finally {
            $rawData = $this->driver->collect();
        }

        $coverage = CoverageResult::fromRawData($rawData, $this->filter);

        return $result->withAttribute(CoverageResult::class, $coverage);
    }
}
