<?php

declare(strict_types=1);

namespace Tests\Fixture\Factory;

final class Dependency
{
    public function __construct(
        public readonly string $status = 'initialized',
    ) {}
}
