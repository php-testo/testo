<?php

declare(strict_types=1);

namespace Tests\Fiber\Self;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Fiber\Coroutine;
use Testo\Fiber\RunInFiber;
use Testo\Test;

/**
 * Self-test: the {@see Coroutine} helpers inside a single method-level `#[RunInFiber]` test —
 * spawn/await round-trips, `concurrently()` result keying, and fiber handles as spawn bodies.
 */
#[Test]
#[Covers(Coroutine::class)]
final class CoroutineScopeTest
{
    #[RunInFiber]
    public function spawnAndAwaitRoundTrip(): void
    {
        $ping = Coroutine::spawn(static function (): string {
            \Fiber::suspend();

            return 'pong';
        });

        Assert::false($ping->isFinished());
        Assert::same($ping->await(), 'pong');
        Assert::true($ping->isFinished());
    }

    #[RunInFiber]
    public function concurrentlyKeepsArgumentKeys(): void
    {
        $results = Coroutine::concurrently(
            pull: static function (): string {
                \Fiber::suspend();

                return 'pulled';
            },
            push: static fn(): string => 'pushed',
        );

        Assert::same($results, ['pull' => 'pulled', 'push' => 'pushed']);
    }

    #[RunInFiber]
    public function acceptsAPreparedFiber(): void
    {
        $fiber = new \Fiber(static function (): int {
            \Fiber::suspend();

            return 7;
        });

        Assert::same(Coroutine::spawn($fiber)->await(), 7);
        Assert::true($fiber->isTerminated());
    }
}
