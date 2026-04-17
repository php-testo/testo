<?php

declare(strict_types=1);

namespace Tests\Repeat\Unit;

use Testo\Assert;
use Testo\Assert\ExpectException;
use Testo\Repeat;
use Testo\Test;

#[Test]
final class RepeatTest
{
    public function defaultTimesIsTwo(): void
    {
        // Act
        $repeat = new Repeat();

        // Assert
        Assert::same($repeat->times, 2);
        Assert::same($repeat->failureThreshold, 1);
    }

    public function customTimes(): void
    {
        // Act
        $repeat = new Repeat(times: 5);

        // Assert
        Assert::same($repeat->times, 5);
    }

    public function timesOneIsValid(): void
    {
        // Act
        $repeat = new Repeat(times: 1);

        // Assert
        Assert::same($repeat->times, 1);
    }

    public function customFailureThreshold(): void
    {
        // Act
        $repeat = new Repeat(times: 5, failureThreshold: 3);

        // Assert
        Assert::same($repeat->failureThreshold, 3);
    }

    public function positionalConstructorArguments(): void
    {
        // Act
        $repeat = new Repeat(10, 3);

        // Assert
        Assert::same($repeat->times, 10);
        Assert::same($repeat->failureThreshold, 3);
    }

    #[ExpectException(\InvalidArgumentException::class)]
    public function zeroTimesThrowsException(): void
    {
        new Repeat(times: 0);
    }

    #[ExpectException(\InvalidArgumentException::class)]
    public function negativeTimesThrowsException(): void
    {
        new Repeat(times: -1);
    }

    #[ExpectException(\InvalidArgumentException::class)]
    public function zeroFailureThresholdThrowsException(): void
    {
        new Repeat(failureThreshold: 0);
    }

    #[ExpectException(\InvalidArgumentException::class)]
    public function negativeFailureThresholdThrowsException(): void
    {
        new Repeat(failureThreshold: -1);
    }
}
