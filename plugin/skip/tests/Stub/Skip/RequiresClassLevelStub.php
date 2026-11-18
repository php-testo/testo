<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\Skip;

use Testo\Assert;
use Testo\Requires;
use Testo\Skip;
use Testo\Test;

/**
 * A class-level `#[Requires]` that is met adds up with the method-level ones and is checked once for
 * the whole case; a `#[Skip]` wins over requirements without any of them being checked: a checked
 * one would throw.
 */
#[Test]
#[Requires([self::class, 'met'])]
final class RequiresClassLevelStub
{
    public static int $classChecks = 0;

    public function runsUnderMetClassRequirement(): void
    {
        Assert::true(true);
    }

    #[Requires([self::class, 'unmet'], 'method requirement')]
    public function skippedByOwnRequirement(): void
    {
        throw new \LogicException('Must never run: the requirement is not met.');
    }

    #[Skip('parked')]
    #[Requires([self::class, 'throws'])]
    public function skipWinsOverRequirement(): void
    {
        throw new \LogicException('Must never run: the test is skipped.');
    }

    private static function met(): bool
    {
        ++self::$classChecks;
        return true;
    }

    private static function unmet(): bool
    {
        return false;
    }

    private static function throws(): bool
    {
        throw new \RuntimeException('Must never be checked: the test is skipped.');
    }
}
