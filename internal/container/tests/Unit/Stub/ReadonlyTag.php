<?php

declare(strict_types=1);

namespace Internal\Container\Tests\Unit\Stub;

/**
 * Readonly service — a container scope shares it with its parent instead of cloning it.
 *
 * @internal
 */
final readonly class ReadonlyTag
{
    public function __construct(
        public int $value = 7,
    ) {}
}
