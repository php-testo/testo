<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\Skip;

use Testo\Assert;
use Testo\Lifecycle\AfterClass;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeClass;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Testo\Skip;

/**
 * Stub for verifying the hooks of a partially skipped case: the case still has a test to run, so
 * the `#[BeforeClass]`/`#[AfterClass]` hooks fire once per case run, while
 * `#[BeforeTest]`/`#[AfterTest]` fire for the enabled control test {@see enabled()} alone, since
 * the skipped test is flagged ahead of the run and reported at the entry of its pipeline.
 * Driven by {@see \Tests\Skip\Feature\SkipFeatureTest::classHooksRunButTestHooksDoNot()}.
 *
 * Static hook counters accumulate across directory runs — feature tests assert deltas.
 */
#[Test]
final class SkipWithHooksStub
{
    public static int $beforeClassCalls = 0;
    public static int $afterClassCalls = 0;
    public static int $beforeTestCalls = 0;
    public static int $afterTestCalls = 0;

    #[BeforeClass]
    public static function bootCase(): void
    {
        ++self::$beforeClassCalls;
    }

    #[AfterClass]
    public static function shutdownCase(): void
    {
        ++self::$afterClassCalls;
    }

    #[BeforeTest]
    public static function bootTest(): void
    {
        ++self::$beforeTestCalls;
    }

    #[AfterTest]
    public static function shutdownTest(): void
    {
        ++self::$afterTestCalls;
    }

    #[Skip('skipped next to hooks')]
    public function skipped(): void
    {
        throw new \LogicException('Must never run: the test is skipped.');
    }

    public function enabled(): void
    {
        # Control neighbor: proves the per-test hooks and counters do work in this case.
        Assert::true(true);
    }
}
