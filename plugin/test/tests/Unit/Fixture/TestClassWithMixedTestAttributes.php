<?php

declare(strict_types=1);

namespace Tests\Test\Unit\Fixture;

use Testo\Test;

#[Test]
final class TestClassWithMixedTestAttributes
{
    public function voidMethod(): void {}

    #[Test]
    public function intMethod(): int
    {
        return 0;
    }

    public function stringMethod(): string
    {
        return '';
    }
}
