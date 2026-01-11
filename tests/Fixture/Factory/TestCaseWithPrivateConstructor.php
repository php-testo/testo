<?php

declare(strict_types=1);

namespace Tests\Fixture\Factory;

final class TestCaseWithPrivateConstructor
{
    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }
}
