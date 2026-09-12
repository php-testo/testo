<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\Skip;

use Testo\Test;
use Testo\Skip;

/**
 * Only skipped tests and no class-level hooks: the class must never be instantiated.
 *
 * The flag is a one-way latch — nothing resets it, so
 * {@see \Tests\Skip\Feature\SkipFeatureTest::fullySkippedCaseWithoutHooksIsNeverInstantiated()}
 * asserts it absolutely, not as a delta.
 */
#[Test]
#[Skip('fully skipped, must not construct')]
final class SkipConstructorSpyStub
{
    public static bool $constructed = false;

    public function __construct()
    {
        self::$constructed = true;
    }

    public function firstSkipped(): void
    {
        throw new \LogicException('Must never run: the case is skipped.');
    }

    public function secondSkipped(): void
    {
        throw new \LogicException('Must never run: the case is skipped.');
    }
}
