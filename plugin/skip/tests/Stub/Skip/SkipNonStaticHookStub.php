<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\Skip;

use Testo\Lifecycle\BeforeClass;
use Testo\Test;
use Testo\Skip;

/**
 * Documented caveat: a non-static class-level hook forces construction even when every
 * test of the case is skipped. The stub pins that behavior so a future change is a
 * conscious one, not an accident.
 *
 * The construction counter accumulates across directory runs —
 * {@see \Tests\Skip\Feature\SkipFeatureTest::nonStaticClassHookStillBuildsTheClass()} asserts the delta.
 */
#[Test]
#[Skip('fully skipped, but the non-static hook builds the class')]
final class SkipNonStaticHookStub
{
    public static int $constructions = 0;

    public function __construct()
    {
        ++self::$constructions;
    }

    #[BeforeClass]
    public function bootCase(): void {}

    public function skipped(): void
    {
        throw new \LogicException('Must never run: the case is skipped.');
    }
}
