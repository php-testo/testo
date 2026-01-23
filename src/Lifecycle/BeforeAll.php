<?php

declare(strict_types=1);

namespace Testo\Lifecycle;

use Testo\Lifecycle\Interceptor\LifecycleInterceptor;
use Testo\Lifecycle\Internal\LifecycleAttribute;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Marks a method to be executed once before all tests in the test case.
 *
 * Typically used for expensive setup operations that can be shared across all tests.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
#[FallbackInterceptor(LifecycleInterceptor::class)]
final class BeforeAll implements Interceptable, LifecycleAttribute
{
    public function __construct(
        /**
         * The priority of the method.
         * Higher priority methods are executed first.
         */
        public readonly int $priority = 0,
    ) {}
}
