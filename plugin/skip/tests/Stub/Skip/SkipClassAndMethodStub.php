<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\Skip;

use Testo\Test;
use Testo\Skip;

/**
 * Stub for verifying which {@see Skip} reason a test is skipped with: a method-level attribute
 * wins over the class-level one, and a method without its own attribute inherits the class reason.
 *
 * The method-level attribute wins as a whole, so {@see self::emptyOwnReason()} is skipped with no
 * reason at all instead of falling back to the class one.
 */
#[Test]
#[Skip('class-wide reason')]
final class SkipClassAndMethodStub
{
    #[Skip('method-specific reason')]
    public function ownReason(): void
    {
        throw new \LogicException('Must never run: the test is skipped.');
    }

    public function classReason(): void
    {
        throw new \LogicException('Must never run: the case is skipped.');
    }

    #[Skip]
    public function emptyOwnReason(): void
    {
        throw new \LogicException('Must never run: the test is skipped.');
    }
}
