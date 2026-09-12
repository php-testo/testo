<?php

declare(strict_types=1);

namespace Tests\Common\Stub;

#[\Attribute(\Attribute::TARGET_CLASS_CONSTANT | \Attribute::IS_REPEATABLE)]
final class CaseMarkerAttribute
{
    public function __construct(
        public readonly string $label = '',
    ) {}
}
