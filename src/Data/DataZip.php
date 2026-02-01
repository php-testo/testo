<?php

declare(strict_types=1);

namespace Testo\Data;

use Testo\Data\Internal\DataProviderAttribute;
use Testo\Data\Internal\DataProviderInterceptor;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Zips multiple data providers together.
 *
 * Each data set will contain one value from each provider, combined into an array.
 * If the providers have different lengths, `null` will be used for missing values.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE)]
#[FallbackInterceptor(DataProviderInterceptor::class)]
final class DataZip implements Interceptable, DataProviderAttribute
{
    /**
     * @param array<DataProvider> $providers Data providers to zip together.
     */
    public readonly array $providers;

    public function __construct(DataProvider ...$providers)
    {
        $this->providers = $providers;
    }
}
