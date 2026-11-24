<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\Skip;

use Testo\Assert;
use Testo\Requires;
use Testo\Test;

/**
 * Method-level `#[Requires]` in every form an attribute argument takes before PHP 8.5. The
 * conditions are private on purpose: they are reachable only through reflection.
 */
#[Test]
final class RequiresStub
{
    public static int $countedCalls = 0;

    #[Requires([self::class, 'unmet'], 'needs the unmet thing')]
    public function skippedByArrayCondition(): void
    {
        throw new \LogicException('Must never run: the requirement is not met.');
    }

    #[Requires(self::class . '::unmet')]
    public function skippedByStringMethodCondition(): void
    {
        throw new \LogicException('Must never run: the requirement is not met.');
    }

    #[Requires('Tests\Skip\Stub\Skip\unmetRequirement')]
    public function skippedByFunctionCondition(): void
    {
        throw new \LogicException('Must never run: the requirement is not met.');
    }

    #[Requires('unmet')]
    public function skippedByBareMethodName(): void
    {
        throw new \LogicException('Must never run: the requirement is not met.');
    }

    #[Requires([self::class])]
    public function malformedArrayCondition(): void
    {
        throw new \LogicException('Must never run: the condition is malformed.');
    }

    #[Requires('self::unmet')]
    public function skippedBySelfMethodString(): void
    {
        throw new \LogicException('Must never run: the requirement is not met.');
    }

    #[Requires([self::class, 'met'])]
    #[Requires('gc_enabled')]
    public function runsWhenEveryRequirementIsMet(): void
    {
        Assert::true(true);
    }

    #[Requires([self::class, 'met'])]
    #[Requires([self::class, 'unmet'])]
    public function skippedWhenOneOfSeveralIsUnmet(): void
    {
        throw new \LogicException('Must never run: one requirement is not met.');
    }

    #[Requires([self::class, 'counted'])]
    public function requirementCheckedOnce(): void
    {
        throw new \LogicException('Must never run: the requirement is not met.');
    }

    #[Requires('php_sapi_name')]
    public function nonBoolRequirement(): void
    {
        throw new \LogicException('Must never run: the requirement returns a string.');
    }

    #[Requires([self::class, 'throws'])]
    public function throwingRequirement(): void
    {
        throw new \LogicException('Must never run: the requirement throws.');
    }

    private static function met(): bool
    {
        return true;
    }

    private static function unmet(): bool
    {
        return false;
    }

    private static function counted(): bool
    {
        ++self::$countedCalls;
        return false;
    }

    private static function throws(): bool
    {
        throw new \RuntimeException('broken requirement');
    }
}

/**
 * Lives next to the class it serves: the class file is loaded before its requirements are checked.
 */
function unmetRequirement(): bool
{
    return false;
}
