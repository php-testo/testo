<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\Skip;

use Testo\Lifecycle\BeforeClass;
use Testo\Requires;
use Testo\Test;

/**
 * A class-level `#[Requires]` that throws: the throw is kept, so the probe runs once for the case,
 * every test of it is aborted with the same exception, and no test is left for the class hook.
 */
#[Test]
#[Requires([self::class, 'throws'])]
final class RequiresThrowingClassStub
{
    public static int $calls = 0;
    public static int $beforeClassCalls = 0;

    #[BeforeClass]
    public static function setUpCase(): void
    {
        ++self::$beforeClassCalls;
    }

    public function first(): void
    {
        throw new \LogicException('Must never run: the requirement throws.');
    }

    public function second(): void
    {
        throw new \LogicException('Must never run: the requirement throws.');
    }

    private static function throws(): bool
    {
        ++self::$calls;
        throw new \RuntimeException('probe timed out');
    }
}
