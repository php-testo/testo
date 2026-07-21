<?php

declare(strict_types=1);

namespace Tests\Fiber\Stub;

use Testo\Assert;
use Testo\Fiber\RunInFiber;
use Testo\Test;

/**
 * Scenarios exercised end-to-end by {@see \Tests\Fiber\Feature\StatusTest} to assert how the plugin
 * maps onto Testo statuses.
 */
#[Test]
final class FiberScenarios
{
    #[RunInFiber]
    public function runsInAFiber(): void
    {
        Assert::notNull(\Fiber::getCurrent());
    }

    #[RunInFiber]
    public function failureInsideFiberPropagates(): void
    {
        Assert::same(1, 2);
    }

    public function untaggedRunsOnMainFiber(): void
    {
        Assert::null(\Fiber::getCurrent());
    }
}
