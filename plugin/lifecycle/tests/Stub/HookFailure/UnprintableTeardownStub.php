<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Stub\HookFailure;

use Testo\Assert;
use Testo\Lifecycle\AfterTest;
use Testo\Test;

final class UnprintableTeardownStub
{
    #[AfterTest]
    public function tearDown(): void
    {
        throw new UnprintableException('unprintable teardown');
    }

    #[Test]
    public function passes(): void
    {
        Assert::true(true);
    }
}
