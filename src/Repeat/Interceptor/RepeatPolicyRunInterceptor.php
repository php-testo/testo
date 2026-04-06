<?php

declare(strict_types=1);

namespace Testo\Repeat\Interceptor;

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
#[InterceptorOptions(order: InterceptorOptions::ORDER_DEFAULT, onConflict: ConflictPolicy::Last)]
final readonly class RepeatPolicyRunInterceptor implements TestRunInterceptor
{
    public function __construct(
        private Repeat $options,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $times = $this->options->times;
        for ($i = 0; $i < $times; $i++) {
            $result = $next($info);

            if ($result->status->isFailure()) {
                return $result;
            }
        }

        return $result;
    }
}
