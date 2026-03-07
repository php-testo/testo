<?php

declare(strict_types=1);

namespace Tests\Fixture\Interceptor;

use Testo\Attribute\Test;

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
