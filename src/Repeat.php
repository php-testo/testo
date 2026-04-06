<?php

declare(strict_types=1);

namespace Testo;

use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;
use Testo\Repeat\Interceptor\RepeatPolicyRunInterceptor;

/**
 * Repeat test specified number of times.
 *
 * Repetition policy that can be applied to any test.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::TARGET_CLASS)]
#[FallbackInterceptor(RepeatPolicyRunInterceptor::class)]
final readonly class Repeat implements Interceptable
{
    public function __construct(
        /**
         * Number of times to repeat the test.
         */
        public int $times = 2,
    ) {
        $times > 0 or throw new \InvalidArgumentException('Times must be greater than 0.');
    }
}
