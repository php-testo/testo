<?php

declare(strict_types=1);

namespace Tests\Bridge\Double\Stub;

use JMac\Testing\Double;
use Testo\Assert;
use Testo\Test;

/**
 * A failing test followed by a clean-slate check, run in declaration order through
 * {@see \Testo\Testing\Helper\TestRunner}: proves the interceptor drains Double's pending state even
 * when a test fails, so the next test's own expectation is the only one verified.
 */
final class DoubleResetScenarios
{
    #[Test]
    public function leavesUnmetExpectation(): void
    {
        $double = Double::for(\Countable::class);
        $double->expects('count');
    }

    #[Test]
    public function seesCleanSlate(): void
    {
        // If the previous test's unmet expectation had leaked, verifyAll() would fail this test too.
        // This double's own expectation is fulfilled, so a Passed status proves the slate was drained.
        $double = Double::for(\Countable::class);
        $double->expects('count')->returns(1);

        Assert::same($double->count(), 1);
    }
}
