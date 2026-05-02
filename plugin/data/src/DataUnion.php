<?php

declare(strict_types=1);

namespace Testo\Data;

use Testo\Data\Internal\DataProviderAttribute;
use Testo\Data\Internal\DataProviderInterceptor;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Concatenates multiple data providers into a single sequence.
 *
 * Primarily useful as a nested attribute inside {@see DataCross} or {@see DataZip}
 * to combine multiple providers before crossing or zipping with others.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE)]
#[FallbackInterceptor(DataProviderInterceptor::class)]
final readonly class DataUnion implements Interceptable, DataProviderAttribute
{
    /**
     * @param array<DataProviderAttribute> $providers Data providers to concatenate.
     */
    public array $providers;

    public function __construct(DataProviderAttribute ...$providers)
    {
        $this->providers = $providers;
    }
}
