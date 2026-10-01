<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Stub\HookFailure;

use Testo\Assert;
use Testo\Lifecycle\AfterTest;
use Testo\Test;

/**
 * Static counters accumulate across directory runs — feature tests assert deltas.
 */
final class PassedTestTeardownStub
{
    public static int $afterTestCalls = 0;

    #[AfterTest(priority: 1)]
    public function firstTearDown(): void
    {
        ++self::$afterTestCalls;
        throw new \RuntimeException('first teardown');
    }

    #[AfterTest]
    public function secondTearDown(): void
    {
        ++self::$afterTestCalls;
        throw new \RuntimeException('second teardown');
    }

    #[Test]
    public function passes(): void
    {
        Assert::true(true);
    }
}
