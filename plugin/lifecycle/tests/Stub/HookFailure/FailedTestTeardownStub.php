<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Stub\HookFailure;

use Testo\Assert;
use Testo\Lifecycle\AfterTest;
use Testo\Test;

final class FailedTestTeardownStub
{
    #[AfterTest]
    public function tearDown(): void
    {
        throw new \RuntimeException('teardown of a failed test');
    }

    #[Test]
    public function fails(): void
    {
        Assert::same(1, 2);
    }
}
