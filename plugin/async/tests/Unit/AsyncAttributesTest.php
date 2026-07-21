<?php

declare(strict_types=1);

namespace Tests\Async\Unit;

use Testo\Assert;
use Testo\Async\Concurrent;
use Testo\Async\RunInCoroutine;
use Testo\Async\Strategy;
use Testo\Codecov\Covers;
use Testo\Pipeline\Attribute\Interceptable;
use Testo\Test;

/**
 * Unit checks for the plugin's public attributes: defaults and the self-wiring contract.
 */
#[Test]
#[Covers(RunInCoroutine::class)]
#[Covers(Concurrent::class)]
#[Covers(Strategy::class)]
final class AsyncAttributesTest
{
    public function concurrentDefaultsToSequential(): void
    {
        Assert::same((new Concurrent())->strategy, Strategy::Sequential);
    }

    public function concurrentKeepsGivenStrategy(): void
    {
        Assert::same((new Concurrent(Strategy::Random))->strategy, Strategy::Random);
    }

    public function attributesSelfWireAsInterceptable(): void
    {
        Assert::instanceOf(new RunInCoroutine(), Interceptable::class);
        Assert::instanceOf(new Concurrent(), Interceptable::class);
    }
}
