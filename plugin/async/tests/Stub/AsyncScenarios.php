<?php

declare(strict_types=1);

namespace Tests\Async\Stub;

use Testo\Assert;
use Testo\Async;
use Testo\Test;

/**
 * Scenarios exercised end-to-end by {@see \Tests\Async\Feature\AsyncStatusTest} to assert how the
 * plugin maps onto Testo statuses.
 */
#[Test]
final class AsyncScenarios
{
    #[Async]
    public function asyncRunsOnLoop(): void
    {
        Assert::notNull(\Fiber::getCurrent());
    }

    #[Async]
    public function failureInsideCoroutinePropagates(): void
    {
        Assert::same(1, 2);
    }

    public function untaggedRunsSynchronously(): void
    {
        Assert::null(\Fiber::getCurrent());
    }
}
