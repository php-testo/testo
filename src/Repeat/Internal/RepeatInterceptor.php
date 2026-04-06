<?php

declare(strict_types=1);

namespace Testo\Repeat\Internal;

use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;
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
        \assert($times > 0);
        do {
            $result = $next($info);
        } while (--$times > 0 && !($result->status->isFailure() && $result->status->isCompleted()));

        return $result;
    }
}
