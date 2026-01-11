<?php

declare(strict_types=1);

namespace Testo\Attribute;

/**
 * Marks a class (public methods), or a method or a function as a test.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::TARGET_CLASS)]
final class Test
{
    public function __construct(
        public string $description = '',
    ) {}
}
