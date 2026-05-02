<?php

declare(strict_types=1);

namespace Tests\Test\Unit\Fixture;

use Testo\Test;

#[Test]
final class TestClassWithNeverReturnType
{
    public function voidMethod(): void {}

    public function neverMethod(): never
    {
        throw new \RuntimeException();
    }

    public function stringMethod(): string
    {
        return '';
    }
}