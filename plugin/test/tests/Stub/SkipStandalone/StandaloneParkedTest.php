<?php

declare(strict_types=1);

namespace Tests\Test\Stub\SkipStandalone;

use Testo\Test\Skip;

/**
 * A catalog for the standalone-fallback run: discovered by naming convention alone (no
 * `#[Test]` attribute), executed without `TestPlugin` — only the class-level `#[Skip]`
 * fallback parks these tests. Lives in its own directory so no regular suite picks up
 * the convention-named class.
 */
#[Skip('standalone catalog is parked')]
final class StandaloneParkedTest
{
    public function testFirstParked(): void
    {
        throw new \LogicException('Must never run: the case is parked via the fallback.');
    }

    public function testSecondParked(): void
    {
        throw new \LogicException('Must never run: the case is parked via the fallback.');
    }
}
