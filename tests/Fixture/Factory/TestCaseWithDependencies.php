<?php

declare(strict_types=1);

namespace Tests\Fixture\Factory;

final class TestCaseWithDependencies
{
    public function __construct(
        public readonly Dependency $dependency,
    ) {}
}
