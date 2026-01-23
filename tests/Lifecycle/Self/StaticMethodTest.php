<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Self;

use Testo\Application\Attribute\Test;
use Testo\Assert;
use Testo\Lifecycle\BeforeEach;

/**
 * Self-tests for static lifecycle methods.
 */
final class StaticMethodTest
{
    public static bool $staticBeforeCalled = false;

    #[BeforeEach]
    public static function staticBefore(): void
    {
        self::$staticBeforeCalled = true;
    }

    #[Test]
    public function staticBeforeMethodIsCalled(): void
    {
        Assert::true(self::$staticBeforeCalled);
    }
}
