<?php

declare(strict_types=1);

namespace Testo\Bridge\Mockery\Internal;

use Mockery;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;

/**
 * Calls {@see Mockery::close()} in a `finally` block after every test so that:
 * - unfulfilled expectations are reported as test failures, and
 * - the Mockery container is cleared between tests, preventing state leak.
 *
 * Runs at {@see PHP_INT_MAX} order, placing it as the innermost interceptor
 * so the teardown fires as close as possible to the actual test function.
 *
 * @internal
 * @psalm-internal Testo\Bridge\Mockery
 */
#[InterceptorOptions(
    order: PHP_INT_MAX,
    testType: TestType::Test,
)]
final readonly class MockeryInterceptor implements TestRunInterceptor
{
    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        try {
            return $next($info);
        } finally {
            Mockery::close();
        }
    }
}
