<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Middleware;

use Testo\Assert\ExpectNoAssertions;
use Testo\Assert\Internal\StaticState;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Assert\State\Record;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;

/**
 * Interceptor to handle expected exceptions.
 *
 * @note Must be placed right before the test execution.
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_ASSERTIONS + 2000, onConflict: ConflictPolicy::First)]
final readonly class ExpectationsInterceptor implements TestRunInterceptor
{
    /**
     * @throws AssertionException When the expected exception is not thrown.
     */
    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        /** @var TestResult $result */
        $result = $next($info);

        # Convert Error status to Failed if caused by an assertion failure
        $result->status === Status::Error && $result->failure instanceof Record and $result = $result
            ->with(status: Status::Failed);

        $state = StaticState::current();

        # No state available
        if ($state === null) {
            return $result;
        }

        while (\count($state->expectations) > 0) {
            # Fetch and remove the last expectation
            $expectation = \array_pop($state->expectations);
            $result = $expectation($result, $state);
        }

        # A successful test is Risky when its assertion activity contradicts the declared intent:
        #   - it recorded no assertion and is NOT marked #[ExpectNoAssertions] (forgotten assert);
        #   - it IS marked #[ExpectNoAssertions] yet recorded assertions (stale/misapplied mark).
        $performedAssertions = $state->history !== [];
        $declaredNoAssertions = $info->getAttribute(ExpectNoAssertions::class) !== null;

        return $result->status === Status::Passed && $performedAssertions === $declaredNoAssertions
            ? $result->with(status: Status::Risky)
            : $result;
    }
}
