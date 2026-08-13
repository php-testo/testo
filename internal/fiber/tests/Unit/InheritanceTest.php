<?php

declare(strict_types=1);

namespace Internal\Fiber\Tests\Unit;

use Internal\Fiber\FiberLocal;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * What a fiber sees when it has no binding of its own.
 *
 * PHP gives a fiber no link to whoever created it, so a child fiber cannot ask its parent for a value.
 * Instead {@see FiberLocal} falls back **when the answer is unambiguous**: if exactly one fiber currently
 * holds a binding, everything running is under it, so an unbound fiber reads that value; if nothing is
 * bound to any fiber, the main thread's binding is used. As soon as two or more fibers hold bindings —
 * i.e. tests are genuinely interleaving — the fallback switches off and the default is returned, because
 * picking one of several concurrent scopes would be a guess.
 *
 * The strict per-fiber binding semantics live in {@see FiberLocalTest}; this covers only the fallback.
 */
#[Test]
#[Covers(FiberLocal::class)]
final class InheritanceTest
{
    public function childFiberInheritsTheSoleFiberBinding(): void
    {
        $local = new FiberLocal('default');
        $seen = null;

        $fiber = new \Fiber(static function () use ($local, &$seen): void {
            $local->scope('in-fiber', static function () use ($local, &$seen): void {
                $child = new \Fiber(static fn(): mixed => $local->get());
                $child->start();
                $seen = $child->getReturn();
            });
        });
        $fiber->start();

        Assert::same($seen, 'in-fiber');
    }

    /**
     * The plain synchronous case: a test runs on the main thread and spawns a fiber. No fiber holds a
     * binding, so the main thread's is the only candidate.
     */
    public function childFiberInheritsTheMainThreadBinding(): void
    {
        $local = new FiberLocal('default');

        $seen = $local->scope('on-main', static function () use ($local): mixed {
            $child = new \Fiber(static fn(): mixed => $local->get());
            $child->start();

            return $child->getReturn();
        });

        Assert::same($seen, 'on-main');
    }

    /**
     * A fiber binding is nearer than the main thread's: with a suite-level scope open on the main thread
     * and one test running in its own fiber, a fiber spawned by that test belongs to the test.
     */
    public function theSoleFiberBindingWinsOverTheMainThreadBinding(): void
    {
        $local = new FiberLocal('default');
        $seen = null;

        $local->scope('on-main', static function () use ($local, &$seen): void {
            $fiber = new \Fiber(static function () use ($local, &$seen): void {
                $local->scope('in-fiber', static function () use ($local, &$seen): void {
                    $child = new \Fiber(static fn(): mixed => $local->get());
                    $child->start();
                    $seen = $child->getReturn();
                });
            });
            $fiber->start();
        });

        Assert::same($seen, 'in-fiber');
    }

    public function inheritanceReachesThroughSeveralFiberGenerations(): void
    {
        $local = new FiberLocal('default');
        $seen = null;

        $fiber = new \Fiber(static function () use ($local, &$seen): void {
            $local->scope('in-fiber', static function () use ($local, &$seen): void {
                $child = new \Fiber(static function () use ($local, &$seen): void {
                    $grandchild = new \Fiber(static fn(): mixed => $local->get());
                    $grandchild->start();
                    $seen = $grandchild->getReturn();
                });
                $child->start();
            });
        });
        $fiber->start();

        Assert::same($seen, 'in-fiber');
    }

    /**
     * Inheritance follows the binding in force right now, not the one the scope started with — the nested
     * case a container scope opened inside a test relies on.
     */
    public function childFiberInheritsTheInnermostNestedValue(): void
    {
        $local = new FiberLocal('default');
        $seen = null;

        $fiber = new \Fiber(static function () use ($local, &$seen): void {
            $local->scope('outer', static function () use ($local, &$seen): void {
                $local->scope('inner', static function () use ($local, &$seen): void {
                    $child = new \Fiber(static fn(): mixed => $local->get());
                    $child->start();
                    $seen = $child->getReturn();
                });
            });
        });
        $fiber->start();

        Assert::same($seen, 'inner');
    }

    public function aFiberWithItsOwnBindingDoesNotInherit(): void
    {
        $local = new FiberLocal('default');
        $seen = null;

        $fiber = new \Fiber(static function () use ($local, &$seen): void {
            $local->scope('parent', static function () use ($local, &$seen): void {
                $child = new \Fiber(static fn(): mixed => $local->scope(
                    'own',
                    static fn(): mixed => $local->get(),
                ));
                $child->start();
                $seen = $child->getReturn();
            });
        });
        $fiber->start();

        Assert::same($seen, 'own');
    }

    /**
     * The safety property: while several tests interleave, each holding its own binding, an unbound fiber
     * has no unambiguous owner — guessing one would attribute its state to the wrong test.
     */
    public function anUnboundFiberFallsBackToTheDefaultWhileSeveralFibersAreBound(): void
    {
        $local = new FiberLocal('default');

        $first = new \Fiber(static function () use ($local): void {
            $local->scope('A', static fn(): mixed => \Fiber::suspend());
        });
        $second = new \Fiber(static function () use ($local): void {
            $local->scope('B', static fn(): mixed => \Fiber::suspend());
        });
        $first->start();
        $second->start();

        $probe = new \Fiber(static fn(): mixed => $local->get());
        $probe->start();

        Assert::same($probe->getReturn(), 'default');

        $first->resume();
        $second->resume();
    }

    /**
     * The fallback only ever answers *inside* a fiber: the main thread reading its own empty slot is not a
     * missing parent, it is simply code running outside every scope.
     */
    public function theMainThreadDoesNotInheritFromAFiber(): void
    {
        $local = new FiberLocal('default');

        $fiber = new \Fiber(static function () use ($local): void {
            $local->scope('in-fiber', static fn(): mixed => \Fiber::suspend());
        });
        $fiber->start();

        Assert::same($local->get(), 'default');

        $fiber->resume();
    }

    public function anUnboundFiberReadsTheDefaultWhenNothingIsBoundAnywhere(): void
    {
        $local = new FiberLocal('default');

        $child = new \Fiber(static fn(): mixed => $local->get());
        $child->start();

        Assert::same($child->getReturn(), 'default');
    }
}
