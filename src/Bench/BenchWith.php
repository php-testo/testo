<?php

declare(strict_types=1);

namespace Testo\Bench;

use Testo\Bench\Internal\BenchWithInterceptor;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Attribute to specify additional functions to benchmark with.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE)]
#[FallbackInterceptor(BenchWithInterceptor::class)]
final class BenchWith implements Interceptable
{
    /**
     * @param array<callable|array{class-string, non-empty-string}> $callables Functions to benchmark with.
     *        It might be a callable or an array with class name and non-public method name.
     */
    public function __construct(
        public readonly array $callables,
        public readonly array $arguments = [],
    ) {}
}
