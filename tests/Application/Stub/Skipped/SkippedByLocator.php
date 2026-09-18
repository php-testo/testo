<?php

declare(strict_types=1);

namespace Tests\Application\Stub\Skipped;

use Testo\Assert;
use Testo\Test;

/**
 * A case whose `flagged` test is marked skipped by {@see FlagSkippedPlugin} at location time,
 * without any attribute of its own: what the core does with a bare
 * {@see \Testo\Core\Definition\TestDefinition::$skipped} flag when no interceptor reports on it.
 */
#[Test]
final class SkippedByLocator
{
    public static bool $flaggedRan = false;

    public function flagged(): void
    {
        self::$flaggedRan = true;
        throw new \LogicException('Must never run: the definition is flagged skipped.');
    }

    public function enabled(): void
    {
        Assert::true(true);
    }
}
