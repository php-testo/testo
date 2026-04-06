<?php

declare(strict_types=1);

namespace Testo;

use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;
use Testo\Retry\Interceptor\RetryPolicyRunInterceptor;

/**
 * Retry test on failure.
 *
 * A universal retry policy that can be applied to any test.
 * When combined with {@see Repeat}, Retry wraps Repeat: each retry attempt
 * runs the full repeat cycle. If any repetition fails, Retry decides whether to try again.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::TARGET_CLASS)]
#[FallbackInterceptor(RetryPolicyRunInterceptor::class)]
final readonly class Retry implements Interceptable
{
    /**
     * @param int<1, max> $maxAttempts Maximum number of attempts.
     * @param bool $markFlaky Mark the test as flaky if it passed on retry.
     */
    public function __construct(
        public int $maxAttempts = 3,
        public bool $markFlaky = true,
    ) {}
}
