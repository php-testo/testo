<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Stub\HookFailure;

use Testo\Assert;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

/**
 * Static counters accumulate across directory runs — feature tests assert deltas.
 */
final class SetupFailureStub
{
    public static int $secondSetUpCalls = 0;
    public static int $bodyCalls = 0;
    public static int $afterTestCalls = 0;

    #[BeforeTest(priority: 1)]
    public function firstSetUp(): void
    {
        throw new \RuntimeException('setup');
    }

    #[BeforeTest]
    public function secondSetUp(): void
    {
        ++self::$secondSetUpCalls;
    }

    #[AfterTest]
    public function tearDown(): void
    {
        ++self::$afterTestCalls;
        throw new \RuntimeException('teardown after a failed setup');
    }

    #[Test]
    public function body(): void
    {
        ++self::$bodyCalls;
        Assert::true(true);
    }
}
