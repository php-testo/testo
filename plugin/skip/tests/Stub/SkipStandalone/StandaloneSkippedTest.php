<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\SkipStandalone;

use Testo\Assert;
use Testo\Skip;

/**
 * The case of the standalone runs: discovered by naming convention alone (no `#[Test]`
 * attribute, no `TestPlugin`), so nothing but the attribute's own fallback declaration
 * wires the skip; one of the runs also leaves the `SkipPlugin` out. Lives in its own
 * directory so the standalone runs' `FinderConfig` can point at it alone.
 */
final class StandaloneSkippedTest
{
    public static bool $enabledRan = false;

    #[Skip('standalone method is skipped')]
    public function testSkipped(): void
    {
        throw new \LogicException('Must never run: the test is skipped via the fallback.');
    }

    public function testEnabled(): void
    {
        self::$enabledRan = true;
        Assert::true(true);
    }
}
