<?php

declare(strict_types=1);

namespace Testo\Data;

use Testo\Data\Internal\DataProviderAttribute;
use Testo\Data\Internal\DataProviderInterceptor;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Attribute to specify a data provider for the test.
 *
 * The data provider should be a callable that returns an iterable of argument sets.
 * Each argument set will be used to invoke the test method separately.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE)]
#[FallbackInterceptor(DataProviderInterceptor::class)]
final readonly class DataProvider implements Interceptable, DataProviderAttribute
{
    public \Closure|string $provider;

    /**
     * @param callable(): iterable<array>|non-empty-string $provider The data provider callable or the method name.
     */
    public function __construct(
        callable|string $provider,
    ) {
        $this->provider = \is_callable($provider) ? $provider(...) : $provider;
    }
}
