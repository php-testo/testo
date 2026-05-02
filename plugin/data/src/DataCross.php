<?php

declare(strict_types=1);

namespace Testo\Data;

use Testo\Data\Internal\DataProviderAttribute;
use Testo\Data\Internal\DataProviderInterceptor;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Crosses multiple data providers together.
 *
 * Each data set will contain one value from each provider, combined into an array.
 * All possible combinations of values from the providers will be generated.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE)]
#[FallbackInterceptor(DataProviderInterceptor::class)]
final readonly class DataCross implements Interceptable, DataProviderAttribute
{
    /**
     * @param array<DataProviderAttribute> $providers Data providers to cross together.
     */
    public array $providers;

    public function __construct(DataProviderAttribute ...$providers)
    {
        $this->providers = $providers;
    }
}
