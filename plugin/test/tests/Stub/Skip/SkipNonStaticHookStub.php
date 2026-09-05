<?php

declare(strict_types=1);

namespace Tests\Test\Stub\Skip;

use Testo\Lifecycle\BeforeClass;
use Testo\Test;
use Testo\Test\Skip;

/**
 * Documented caveat: a non-static class-level hook forces construction even when every
 * test of the case is parked. The stub pins that behavior so a future change is a
 * conscious one, not an accident.
 *
 * The construction counter accumulates across catalog runs — feature tests assert deltas.
 */
#[Test]
#[Skip('fully parked, but the non-static hook builds the class')]
final class SkipNonStaticHookStub
{
    public static int $constructions = 0;

    public function __construct()
    {
        ++self::$constructions;
    }

    #[BeforeClass]
    public function bootCase(): void {}

    public function parked(): void
    {
        throw new \LogicException('Must never run: the case is parked.');
    }
}
