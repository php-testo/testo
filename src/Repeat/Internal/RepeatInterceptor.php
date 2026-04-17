<?php

declare(strict_types=1);

namespace Testo\Repeat\Internal;

use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;
use Testo\Core\Value\Status;
use Testo\Repeat;

/**
 * Interceptor that repeats a test execution based on the provided repeat policy.
 *
 * @see Repeat
 *
 * @api
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_DEFAULT - 190, onConflict: ConflictPolicy::Last)]
final readonly class RepeatInterceptor implements TestRunInterceptor
{
    public function __construct(
        private Repeat $options,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $times = $this->options->times;
        $failureThreshold = $this->options->failureThreshold;
        $failures = 0;
        \assert($times > 0);
        \assert($failureThreshold > 0);

        while ($times-- > 0) {
            $result = $next($info);
            if (!$result->status->isCompleted()) {
                return $result;
            }

            if (!$result->status->isFailure()) {
                continue;
            }

            if (++$failures >= $failureThreshold) {
                return $result;
            }
        }

        if ($failures > 0) {
            return $result
                ->with(status: Status::Passed)
                ->withFailure(null);
        }

        return $result;
    }
}
