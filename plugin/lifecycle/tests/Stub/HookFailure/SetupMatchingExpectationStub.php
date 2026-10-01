<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Stub\HookFailure;

use Testo\Assert\ExpectException;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

final class SetupMatchingExpectationStub
{
    #[BeforeTest]
    public function setUp(): void
    {
        throw new \RuntimeException('setup');
    }

    #[Test]
    #[ExpectException(\RuntimeException::class)]
    public function expectsRuntimeException(): void {}
}
