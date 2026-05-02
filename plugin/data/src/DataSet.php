<?php

declare(strict_types=1);

namespace Testo\Data;

use Testo\Data\Internal\DataProviderAttribute;
use Testo\Data\Internal\DataProviderInterceptor;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Attribute to specify a data set for the test.
 *
 * Each data set is an array of arguments that will be passed to the test method.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE)]
#[FallbackInterceptor(DataProviderInterceptor::class)]
final readonly class DataSet implements Interceptable, DataProviderAttribute
{
    /**
     * @param array $arguments The arguments for the data set.
     *        Can be an associative array to specify named arguments.
     * @param string|null $name An optional name for the data set.
     */
    public function __construct(
        public array $arguments,
        public ?string $name = null,
    ) {}
}
