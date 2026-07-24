<?php

declare(strict_types=1);

namespace Internal\Container\Tests\Unit;

use Internal\Container\ObjectContainer;
use Internal\Container\Tests\Testo\Loop;
use Internal\Container\Tests\Unit\Stub\ContainerScopeService;
use Revolt\EventLoop;
use stdClass;
use Testo\Assert;
use Testo\Bridge\Revolt\RunInRevolt;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * Harness sanity for probing {@see ObjectContainer} under a real Revolt event loop.
 *
 * These first cases only prove the {@see Loop} harness itself — that a body runs inside a
 * driver-managed fiber, that a suspension round-trips, and that the container resolves inside
 * the loop. The scope-across-suspension behaviour (which today breaks via the fiber
 * trampoline) is added on top of this harness next.
 */
#[Test]
#[Covers(ObjectContainer::class)]
final class ScopesRevoltTest
{
    public function loopRunsBodyInsideAFiberAndReturnsItsValue(): void
    {
        $insideFiber = false;
        $value = Loop::run(function () use (&$insideFiber): string {
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
     * A real Revolt await **inside** a container `scope()`: the scope's state must survive the
     * suspension. The loop resumes this exact fiber directly, and because the active {@see State} is held
     * per fiber (see {@see \Internal\Fiber\FiberLocal}), `get()` after the await still reads the scope's
     * own instance (`tag = 42`), not a fresh parent one. Before the fiber-local migration this leaked via
     * the fiber trampoline (returned `tag = 0`) or deadlocked the Revolt driver.
     */
    #[RunInRevolt]
    public function scopeAcrossARevoltAwaitKeepsItsState(): void
    {
        $container = new ObjectContainer();

        $container->scope(static function (ObjectContainer $scoped): void {
            $scoped->get(ContainerScopeService::class)->tag = 42;

            $suspension = EventLoop::getSuspension();
            EventLoop::delay(0.001, static fn() => $suspension->resume());
            $suspension->suspend();

            Assert::same($scoped->get(ContainerScopeService::class)->tag, 42);
        });
    }
}
