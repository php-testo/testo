<?php

declare(strict_types=1);

namespace Testo\Pipeline\Attribute;

/**
 * Define a fallback interceptor for the {@see Interceptable} attribute.
 *
 * For example:
 *
 * ```
 *  #[\Attribute]
 *  #[FallbackInterceptor(RetryPolicyCallInterceptor::class)]
 *  final class RetryPolicy {}
 * ```
 *
 * Repeatable: an attribute may wire several interceptors, each with its own pipeline position
 * ({@see InterceptorOptions}); every one of them is instantiated with the attribute instance.
 *
 * Makes sense only for interceptors that are executed during tests execution.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class FallbackInterceptor
{
    public function __construct(
        /**
         * Interceptor class that can handle the attribute.
         *
         * @var class-string<\Testo\Pipeline\Interceptor>
         */
        public string $class,
    ) {}
}
