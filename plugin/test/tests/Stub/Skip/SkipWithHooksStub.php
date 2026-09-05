<?php

declare(strict_types=1);

namespace Tests\Test\Stub\Skip;

use Testo\Assert;
use Testo\Lifecycle\AfterClass;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeClass;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Testo\Test\Skip;

/**
 * Static hook counters accumulate across catalog runs — feature tests assert deltas.
 */
#[Test]
final class SkipWithHooksStub
{
    public static int $beforeClass = 0;
    public static int $afterClass = 0;
    public static int $beforeTest = 0;
    public static int $afterTest = 0;

    #[BeforeClass]
    public static function bootCase(): void
    {
        ++self::$beforeClass;
    }

    #[AfterClass]
    public static function shutdownCase(): void
    {
        ++self::$afterClass;
    }

    #[BeforeTest]
    public static function bootTest(): void
    {
        ++self::$beforeTest;
    }

    #[AfterTest]
    public static function shutdownTest(): void
    {
        ++self::$afterTest;
    }

    #[Skip('parked next to hooks')]
    public function parked(): void
    {
        throw new \LogicException('Must never run: the test is parked.');
    }

    public function enabled(): void
    {
        // Control neighbor: proves the per-test hooks and counters do work in this case.
        Assert::true(true);
    }
}
