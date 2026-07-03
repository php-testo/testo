<?php

declare(strict_types=1);

namespace Tests\Bridge\Mockery\Stub;

use Testo\Assert;
use Testo\Test;

/**
 * A failing test followed by a clean-slate check, run in declaration order through
 * {@see \Testo\Testing\Helper\TestRunner}: proves the interceptor resets the Mockery container even
 * when a test fails, so the next test starts clean.
 */
final class MockeryResetScenarios
{
    #[Test]
    public function leavesUnmetExpectation(): void
    {
        $mock = \Mockery::mock(\Countable::class);
        $mock->expects('count')->once();
    }

    #[Test]
    public function seesCleanContainer(): void
    {
        Assert::same(\Mockery::getContainer()->mockery_getExpectationCount(), 0);
    }
}
