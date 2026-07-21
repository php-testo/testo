<?php

declare(strict_types=1);

namespace Tests\Fiber\Self;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Fiber\Internal\RunInFiberInterceptor;
use Testo\Fiber\RunInFiber;
use Testo\Test;

/**
 * Self-test: a method-level `#[RunInFiber]` (no class-level attribute) wraps just that test in its own
 * fiber, while an untagged test keeps running on the main fiber.
 */
#[Test]
#[Covers(RunInFiber::class)]
#[Covers(RunInFiberInterceptor::class)]
final class MethodLevelTest
{
    #[RunInFiber]
    public function taggedRunsInAFiber(): void
    {
        Assert::notNull(\Fiber::getCurrent());
    }

    public function untaggedRunsOnMainFiber(): void
    {
        Assert::null(\Fiber::getCurrent());
    }
}
