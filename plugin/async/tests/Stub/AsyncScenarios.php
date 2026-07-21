<?php

declare(strict_types=1);

namespace Tests\Async\Stub;

use Testo\Assert;
use Testo\Async\RunInCoroutine;
use Testo\Test;

/**
 * Scenarios exercised end-to-end by {@see \Tests\Async\Feature\AsyncStatusTest} to assert how the
 * plugin maps onto Testo statuses.
 */
#[Test]
final class AsyncScenarios
{
    #[RunInCoroutine]
    public function asyncRunsOnLoop(): void
    {
        Assert::notNull(\Fiber::getCurrent());
    }

    #[RunInCoroutine]
    public function failureInsideCoroutinePropagates(): void
    {
        Assert::same(1, 2);
    }

    public function untaggedRunsSynchronously(): void
    {
        Assert::null(\Fiber::getCurrent());
    }
}
