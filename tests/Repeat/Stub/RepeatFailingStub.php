<?php

declare(strict_types=1);

namespace Tests\Repeat\Stub;

use Testo\Assert;
use Testo\Repeat;
use Testo\Test;

/**
 * Stub with tests that fail during repetition.
 */
final class RepeatFailingStub
{
    private int $reachesFailureThresholdCounter = 0;

    /**
     * Passes first time, fails on second iteration.
     */
    #[Test]
    #[Repeat(times: 3)]
    public function failsOnSecondIteration(): void
    {
        static $counter = 0;
        ++$counter;
        // First call passes, second call fails
        Assert::same($counter, 1);
    }

    /**
     * Fails on first iteration - repeat doesn't help.
     */
    #[Test]
    #[Repeat(times: 3)]
    public function failsImmediately(): void
    {
        Assert::same(1, 2);
    }

    /**
     * Fails twice and reaches the failure threshold.
     */
    #[Test]
    #[Repeat(times: 4, failureThreshold: 2)]
    public function reachesFailureThreshold(): void
    {
        ++$this->reachesFailureThresholdCounter;

        if ($this->reachesFailureThresholdCounter % 2 === 0) {
            Assert::fail('Simulated failure for threshold testing.');
        }
    }
}
