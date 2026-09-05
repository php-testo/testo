<?php

declare(strict_types=1);

namespace Tests\Test\Stub\Skip;

use Testo\Test;
use Testo\Test\Skip;

/**
 * Only parked tests and no class-level hooks: the class must never be instantiated.
 */
#[Test]
#[Skip('fully parked, must not construct')]
final class SkipConstructorSpyStub
{
    public static bool $constructed = false;

    public function __construct()
    {
        self::$constructed = true;
    }

    public function firstParked(): void
    {
        throw new \LogicException('Must never run: the case is parked.');
    }

    public function secondParked(): void
    {
        throw new \LogicException('Must never run: the case is parked.');
    }
}
