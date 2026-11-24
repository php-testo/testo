<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\Skip;

use Testo\Requires;
use Testo\Test;

/**
 * An unmet class-level `#[Requires]` skips every test of the case, so the case is never built.
 */
#[Test]
#[Requires([self::class, 'unmet'], 'class requirement')]
final class RequiresUnmetClassStub
{
    public static bool $constructed = false;

    public function __construct()
    {
        self::$constructed = true;
    }

    public function first(): void
    {
        throw new \LogicException('Must never run: the requirement is not met.');
    }

    public function second(): void
    {
        throw new \LogicException('Must never run: the requirement is not met.');
    }

    private static function unmet(): bool
    {
        return false;
    }
}
