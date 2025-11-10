<?php

declare(strict_types=1);

namespace Testo\Interceptor\TestCallInterceptor;

use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Attribute\RetryPolicy;
use Testo\Interceptor\TestRunInterceptor;
use Testo\Test\Dto\Status;
use Testo\Test\Dto\TestInfo;
use Testo\Test\Dto\TestResult;
use Testo\Test\Event\Test\TestRetrying;

/**
 * Interceptor that retries a test execution based on the provided retry policy.
 *
 * @see RetryPolicy
 */
final class RetryPolicyRunInterceptor implements TestRunInterceptor
{
    public function __construct(
        private readonly RetryPolicy $options,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $attempts = $this->options->maxAttempts;
        $isFlaky = false;

        run:
        --$attempts;
        $attempt = 1;
        /** @var TestResult $result */
        $result = $next($info);

        if ($result->status->isFailure()) {
            # Test failed, check if we can retry
            if ($attempts > 0) {
                $isFlaky = true;
                $this->eventDispatcher->dispatch(
                    new TestRetrying($info, ++$attempt, $result),
                );
                goto run;
            }

            return $result;
        }

        return $isFlaky && $this->options->markFlaky && $result->status->isSuccessful()
            ? $result->with(status: Status::Flaky)
            : $result;
    }
}
