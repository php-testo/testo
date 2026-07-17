<?php

declare(strict_types=1);

namespace Tests\Async\Unit;

use Testo\Assert;
use Testo\Async;
use Testo\Async\Strategy;
use Testo\Codecov\Covers;
use Testo\Concurrent;
use Testo\Pipeline\Attribute\Interceptable;
use Testo\Test;

/**
 * Unit checks for the plugin's public attributes: defaults and the self-wiring contract.
 */
#[Test]
#[Covers(Async::class)]
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
        Assert::instanceOf(new Async(), Interceptable::class);
        Assert::instanceOf(new Concurrent(), Interceptable::class);
    }
}
