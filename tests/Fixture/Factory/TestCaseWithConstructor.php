<?php

declare(strict_types=1);

namespace Tests\Fixture\Factory;

final class TestCaseWithConstructor
{
    public function __construct(
        public readonly string $name,
        public readonly int $value,
    ) {}
}
