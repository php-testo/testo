<?php

declare(strict_types=1);

namespace Internal\Container\Tests\Unit;

use Internal\Container\ObjectContainer;
use Internal\Container\Tests\Testo\Loop;
use Internal\Container\Tests\Unit\Stub\ContainerScopeService;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * {@see ObjectContainer} under a real Revolt event loop.
 *
 * The first cases prove the {@see Loop} harness itself — that a body runs inside a driver-managed
 * fiber, that a suspension round-trips, and that the container resolves inside the loop. The last one
 * states the supported contract: a scope is opened **outside** the loop, and everything running on the
 * loop under it — the body, the coroutines it spawns — resolves through it, because the scope's state is
 * simply the active one for as long as the scope is open. The inverse — opening a scope *inside* a
 * loop-driven fiber and awaiting within it — is not supported: `scope()` would hand-drive a child fiber
 * there, and the Revolt driver resumes that child directly, bypassing the guard.
 */
#[Test]
#[Covers(ObjectContainer::class)]
final class ScopesRevoltTest
{
    public function loopRunsBodyInsideAFiberAndReturnsItsValue(): void
    {
        $insideFiber = false;
        $value = Loop::run(static function () use (&$insideFiber): string {
            $insideFiber = \Fiber::getCurrent() !== null;
            return 'done';
        });

        Assert::true($insideFiber);
        Assert::same($value, 'done');
    }

    public function tickRoundTripsThroughTheDriver(): void
    {
        $log = Loop::run(static function (): array {
            $log = ['before'];
            Loop::tick();
            $log[] = 'after';
            return $log;
        });

        Assert::same($log, ['before', 'after']);
    }

    public function throwableFromBodyPropagatesOutOfTheLoop(): void
    {
        $caught = null;
        try {
            Loop::run(static fn() => throw new \RuntimeException('boom'));
        } catch (\RuntimeException $e) {
            $caught = $e;
        }

        Assert::notNull($caught);
        Assert::same($caught?->getMessage(), 'boom');
    }

    public function containerResolvesInsideTheLoop(): void
    {
        $service = Loop::run(static function (): object {
            $container = new ObjectContainer();
            return $container->get(ContainerScopeService::class);
        });

        Assert::instanceOf($service, ContainerScopeService::class);
    }

    /**
     * The supported shape: the scope is opened on the main thread and the loop runs **inside** it. The
     * body — a loop-driven fiber — and a further coroutine it spawns both resolve through the scope's
     * state across real awaits, and after the scope closes the root resolves its own instance again.
     */
    public function aScopeOpenedOutsideTheLoopReachesTheBodyAndItsCoroutines(): void
    {
        $container = new ObjectContainer();

        $container->scope(static function (ObjectContainer $scoped) use ($container): void {
            $scoped->get(ContainerScopeService::class)->tag = 42;

            [$body, $spawned] = Loop::run(static function () use ($container): array {
                $body = $container->get(ContainerScopeService::class)->tag;
                Loop::tick();

                # A coroutine of the body's own, one level deeper on the loop.
                $spawned = Loop::run(static fn(): ?int => $container->get(ContainerScopeService::class)->tag);

                return [$body, $spawned];
            });

            Assert::same($body, 42);
            Assert::same($spawned, 42);
        });

        Assert::same($container->get(ContainerScopeService::class)->tag, 0);
    }
}
