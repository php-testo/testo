<?php

declare(strict_types=1);

namespace Internal\Fiber\Tests\Unit;

use Internal\Fiber\FiberLocal;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(FiberLocal::class)]
final class FiberLocalTest
{
    public function mainSlotDefaultsThenScopes(): void
    {
        $local = new FiberLocal('default');

        Assert::same($local->get(), 'default');

        $inside = $local->scope('scoped', static fn(): string => $local->get());
        Assert::same($inside, 'scoped');
        Assert::same($local->get(), 'default');
    }

    public function nestedScopeRestoresOuterValue(): void
    {
        $local = new FiberLocal('base');
        $seen = [];

        $local->scope('outer', static function () use ($local, &$seen): void {
            $seen[] = $local->get();
            $local->scope('inner', static function () use ($local, &$seen): void {
                $seen[] = $local->get();
            });
            $seen[] = $local->get();
        });

        Assert::same($seen, ['outer', 'inner', 'outer']);
        Assert::same($local->get(), 'base');
    }

    /**
     * The interleaving invariant every guard relies on: while two fibers are driven turn by turn, each
     * reads its own scoped value across every switch, with no swapping at the suspension boundary.
     */
    public function interleavedFibersEachReadOwnValue(): void
    {
        $local = new FiberLocal('base');
        $log = [];

        $fiberA = new \Fiber(static function () use ($local, &$log): void {
            $local->scope('A', static function () use ($local, &$log): void {
                $log[] = 'A:' . $local->get();
                \Fiber::suspend();
                $log[] = 'A:' . $local->get();
            });
        });
        $fiberB = new \Fiber(static function () use ($local, &$log): void {
            $local->scope('B', static function () use ($local, &$log): void {
                $log[] = 'B:' . $local->get();
                \Fiber::suspend();
                $log[] = 'B:' . $local->get();
            });
        });

        $fiberA->start();
        $fiberB->start();
        $fiberA->resume();
        $fiberB->resume();

        Assert::same($log, ['A:A', 'B:B', 'A:A', 'B:B']);
        Assert::same($local->get(), 'base');
    }

    public function scopeRestoresAfterException(): void
    {
        $local = new FiberLocal('base');

        try {
            $local->scope('inner', static function (): never {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
            // The throw is incidental machinery here — what we verify is the restore in scope()'s finally.
        }

        Assert::same($local->get(), 'base');
    }

    public function scopeRunsDestroyOnceAfterRestore(): void
    {
        $local = new FiberLocal('base');
        $seenDuringDestroy = 'unset';
        $calls = 0;

        $local->scope(
            'scoped',
            static fn(): int => 1,
            static function () use ($local, &$seenDuringDestroy, &$calls): void {
                ++$calls;
                $seenDuringDestroy = $local->get();
            },
        );

        Assert::same($calls, 1);
        Assert::same($seenDuringDestroy, 'base');
    }

    public function scopeRunsDestroyOnException(): void
    {
        $local = new FiberLocal('base');
        $destroyed = false;

        try {
            $local->scope(
                'scoped',
                static function (): never {
                    throw new \RuntimeException('boom');
                },
                static function () use (&$destroyed): void {
                    $destroyed = true;
                },
            );
        } catch (\RuntimeException) {
        }

        Assert::true($destroyed);
    }

    public function abandonedFiberSlotIsReleasedByGc(): void
    {
        $local = new FiberLocal('base');

        // Park a fiber inside scope() so its restoring finally never runs — the slot lingers in the map.
        $fiber = new \Fiber(static function () use ($local): void {
            $local->scope('leaky', static fn() => \Fiber::suspend());
        });
        $fiber->start();

        /** @var \WeakMap<\Fiber, mixed> $byFiber */
        $byFiber = (new \ReflectionProperty(FiberLocal::class, 'byFiber'))->getValue($local);
        Assert::count($byFiber, 1);

        $fiber = null;
        \gc_collect_cycles();

        Assert::count($byFiber, 0);
    }
}
