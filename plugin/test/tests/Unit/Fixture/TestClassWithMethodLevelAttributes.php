<?php

declare(strict_types=1);

namespace Tests\Test\Unit\Fixture;

use Testo\Test;

final class TestClassWithMethodLevelAttributes
{
    #[Test]
    public function methodOne(): void {}

    #[Test]
    protected function nonTestMethodOne(): void {}

    public function nonTestMethodTwo(): void {}

    #[Test]
    private function nonTestMethodThree(): void {}

    #[Test]
    public function methodTwo(): void {}
}
