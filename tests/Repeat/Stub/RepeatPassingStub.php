<?php

declare(strict_types=1);

namespace Tests\Repeat\Stub;

use Testo\Assert;
use Testo\Repeat;
use Testo\Test;

/**
 * Stub with passing repeated tests.
 */
final class RepeatPassingStub
{
    private int $repeatWithToleratedFailuresCounter = 0;

    /**
     * Default repeat (2 times), all pass.
     */
    #[Test]
    #[Repeat]
    public function defaultRepeat(): void
    {
        Assert::true(true);
    }

    /**
     * Repeat 3 times, all pass.
     */
    #[Test]
    #[Repeat(times: 3)]
    public function repeatThreeTimes(): void
    {
        Assert::true(true);
    }

    /**
     * Repeat once - equivalent to a normal test.
     */
    #[Test]
    #[Repeat(times: 1)]
    public function repeatOnce(): void
    {
        Assert::true(true);
    }

    /**
     * Fails occasionally but stays below failure threshold.
     */
    #[Test]
    #[Repeat(times: 4, failureThreshold: 3)]
    public function repeatWithToleratedFailures(): void
    {
        ++$this->repeatWithToleratedFailuresCounter;

        if ($this->repeatWithToleratedFailuresCounter % 2 === 0) {
            Assert::fail('Simulated failure for threshold testing.');
        }
    }
}
