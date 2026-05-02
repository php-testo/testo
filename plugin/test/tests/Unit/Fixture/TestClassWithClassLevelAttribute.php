<?php

declare(strict_types=1);

namespace Tests\Test\Unit\Fixture;

use Testo\Test;

#[Test]
final class TestClassWithClassLevelAttribute
{
    public function methodOne(): void {}

    protected function protectedMethod(): void {}

    public function methodTwo(): void {}

    private function privateMethod(): void {}
}
