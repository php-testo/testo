<?php

declare(strict_types=1);

namespace Tests\Fiber\Unit;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Fiber\RunInFiber;
use Testo\Fiber\Schedule;
use Testo\Pipeline\Attribute\Interceptable;
use Testo\Test;

/**
 * Unit checks for the plugin's public attribute: defaults and the self-wiring contract.
 */
#[Test]
#[Covers(RunInFiber::class)]
#[Covers(Schedule::class)]
final class RunInFiberAttributesTest
{
    public function defaultsToSolo(): void
    {
        Assert::same((new RunInFiber())->schedule, Schedule::Solo);
    }

    public function keepsGivenSchedule(): void
    {
        Assert::same((new RunInFiber(Schedule::Random))->schedule, Schedule::Random);
    }

    public function selfWiresAsInterceptable(): void
    {
        Assert::instanceOf(new RunInFiber(), Interceptable::class);
    }
}
