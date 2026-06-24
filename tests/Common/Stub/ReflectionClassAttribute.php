<?php

declare(strict_types=1);

namespace Tests\Common\Stub;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ReflectionClassAttribute
{
    public function __construct(
        public readonly string $label = '',
    ) {}
}
