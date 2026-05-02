<?php

declare(strict_types=1);

namespace Testo\Assert;

use Testo\Assert\Internal\ExpectExceptionConfigurator;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Expect exception to be thrown.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
#[FallbackInterceptor(ExpectExceptionConfigurator::class)]
final readonly class ExpectException implements Interceptable
{
    /**
     * @param class-string<\Throwable> $class Expected exception class.
     */
    public function __construct(
        public string $class,
    ) {}
}
