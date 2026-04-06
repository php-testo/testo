<?php

declare(strict_types=1);

namespace Testo\Pipeline\Attribute;

use Testo\Pipeline\Policy\ConflictPolicy;

/**
 * Interceptor options.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class InterceptorOptions
{
    /**
     * Handles {@see Interceptable} attributes
     */
    public const ORDER_ATTRIBUTES = -300_000_000_000;

    /**
     * Filtering files, classes, tests, etc.
     */
    public const ORDER_FILTER = -200_000_000;

    public const ORDER_DATA_PROVIDER = -200_000;

    public const ORDER_DEFAULT = 0;

    /**
     * Register assertions and expectations
     */
    public const ORDER_ASSERTIONS = 2_000;

    /**
     * Interceptors that are close to the test function in the interceptor chain.
     */
    public const ORDER_CLOSE_TO_TEST = 200_000_000;

    /**
     * Interceptors that are applied right before the test function.
     */
    public const ORDER_RIGHT_BEFORE_TEST = 300_000_000_000;

    public function __construct(
        /**
         * The priority of the interceptor.
         *
         * Lower priority interceptors are applied first in the interceptor chain.
         * Higher priority interceptors are closer to the test function in the interceptor chain.
         */
        public readonly int $order = self::ORDER_DEFAULT,
        public readonly ConflictPolicy $onConflict = ConflictPolicy::First,

        /**
         * @var list<non-empty-string|\BackedEnum>|non-empty-string|\BackedEnum Type(s) of tests to which
         *      the interceptor should be applied. If empty, the interceptor is applied to all tests.
         */
        public readonly \BackedEnum|array|string $testType = [],
    ) {}
}
