<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Stub\HookFailure;

use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Test;

final class ExpectedExceptionTeardownStub
{
    #[AfterTest]
    public function tearDown(): void
    {
        throw new \LogicException('teardown of a test that met its expectation');
    }

    #[Test]
    public function throwsExpected(): never
    {
        Expect::exception(\RuntimeException::class);

        throw new \RuntimeException('expected by the test');
    }
}
