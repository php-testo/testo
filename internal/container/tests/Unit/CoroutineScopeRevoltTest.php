<?php

declare(strict_types=1);

namespace Internal\Container\Tests\Unit;

use Internal\Container\ObjectContainer;
use Internal\Container\Tests\Unit\Stub\ContainerScopeService;
use Revolt\EventLoop;
use Testo\Assert;
use Testo\Bridge\Revolt\RunInRevolt;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * Container {@see ObjectContainer::scope()} on a real Revolt loop, where {@see RunInRevolt} gives the whole
 * loop run to one test at a time.
 *
 * Two things have to hold there. A scope must survive the test's own awaits: the loop resumes the test's
 * fiber directly, with nothing swapping state back in at the boundary, so the scoped instance has to be
 * found on the fiber itself. And a coroutine the test spawns has to resolve through that same scope — it
 * holds no scope of its own and PHP gives it no link to its creator, so the container can only infer the
 * owner, which it can do exactly because a single test is in flight.
 */
#[Test]
#[RunInRevolt]
#[Covers(ObjectContainer::class)]
final class CoroutineScopeRevoltTest
{
    public function aScopeHoldsAcrossItsOwnAwaits(): void
    {
        (new ObjectContainer())->scope(static function (ObjectContainer $scoped): void {
            $scoped->get(ContainerScopeService::class)->tag = 42;

            for ($i = 0; $i < 3; $i++) {
                self::await();

                Assert::same($scoped->get(ContainerScopeService::class)->tag, 42);
            }
        });
    }

    public function aSpawnedCoroutineResolvesThroughTheSurroundingScope(): void
    {
        $container = new ObjectContainer();

        $container->scope(static function (ObjectContainer $scoped) use ($container): void {
            $scoped->get(ContainerScopeService::class)->tag = 7;

            // The coroutine asks the *root* container, as any code reached from the test would: it must be
            // answered from the scope the test is running in, not from the root's own instance.
            $seen = self::inCoroutine(
                static fn(): ?int => $container->get(ContainerScopeService::class)->tag,
            );

            Assert::same($seen, 7);
        });
    }

    public function aCoroutineWithAScopeOfItsOwnKeepsItSeparate(): void
    {
        $container = new ObjectContainer();

        $container->scope(static function (ObjectContainer $outer) use ($container): void {
            $outer->get(ContainerScopeService::class)->tag = 1;

            $inner = self::inCoroutine(static fn(): ?int => $container->scope(
                static function (ObjectContainer $scoped): ?int {
                    $scoped->get(ContainerScopeService::class)->tag = 2;
                    self::await();

                    return $scoped->get(ContainerScopeService::class)->tag;
                },
            ));

            Assert::same($inner, 2);
            Assert::same($outer->get(ContainerScopeService::class)->tag, 1);
        });
    }

    /**
     * Park on a real timer, letting the loop run while we wait.
     */
    private static function await(): void
    {
        $suspension = EventLoop::getSuspension();
        EventLoop::delay(0.001, static fn() => $suspension->resume());
        $suspension->suspend();
    }

    /**
     * Run $body as a coroutine of its own on the loop, block until it finishes, and hand back its value.
     */
    private static function inCoroutine(\Closure $body): ?int
    {
        $suspension = EventLoop::getSuspension();

        EventLoop::queue(static function () use ($suspension, $body): void {
            try {
                $suspension->resume($body());
            } catch (\Throwable $e) {
                $suspension->throw($e);
            }
        });

        /** @var int|null */
        return $suspension->suspend();
    }
}
