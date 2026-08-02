<?php

declare(strict_types=1);

namespace Internal\Fiber\Tests\Unit;

use Internal\Fiber\DrivenScopes;
use Internal\Fiber\FiberLocal;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

/**
 * {@see FiberLocal::scope()} inside a {@see DrivenScopes} region: the scope runs its body on a fiber the
 * scope itself pumps and publishes its value to every fiber for as long as that body holds the floor.
 *
 * This is what per-fiber binding cannot do — PHP links a fiber to nothing, so fibers the body spawns are
 * unreachable by {@see FiberLocal} alone (see {@see InheritanceTest} for how far the unambiguous fallback
 * gets without driving). The driver takes the value back down whenever the body parks, which is what keeps
 * sibling driven scopes from bleeding into one another.
 */
#[Test]
#[Covers(FiberLocal::class)]
#[Covers(DrivenScopes::class)]
final class DrivenScopeTest
{
    public function aDrivenScopePublishesItsValueToASpawnedFiber(): void
    {
        $local = new FiberLocal('base');

        $observed = DrivenScopes::run(static function () use ($local): mixed {
            $outer = new \Fiber(static fn(): mixed => $local->scope(
                'scoped',
                static function () use ($local): mixed {
                    $child = new \Fiber(static fn(): mixed => $local->get());
                    $child->start();

                    return $child->getReturn();
                },
            ));
            $outer->start();

            return $outer->getReturn();
        });

        Assert::same($observed, 'scoped');
    }

    /**
     * Two driven scopes alive at once, each spawning a fiber after the other has opened: the value in force
     * always belongs to the subtree currently running, so neither reads the other's.
     */
    public function siblingDrivenScopesDoNotSeeEachOther(): void
    {
        $local = new FiberLocal('base');

        $observed = DrivenScopes::run(static function () use ($local): array {
            $open = static fn(string $value): \Fiber => new \Fiber(static fn(): mixed => $local->scope(
                $value,
                static function () use ($local): mixed {
                    // Park with the scope open so the sibling opens its own before either of us reads.
                    \Fiber::suspend();

                    $child = new \Fiber(static fn(): mixed => $local->get());
                    $child->start();

                    return $child->getReturn();
                },
            ));

            $first = $open('A');
            $second = $open('B');
            $first->start();
            $second->start();
            $first->resume();
            $second->resume();

            return [$first->getReturn(), $second->getReturn()];
        });

        Assert::same($observed, ['A', 'B']);
    }

    /**
     * From the outside a driven scope suspends exactly like the body it wraps: the value the body parks
     * with comes out, and the value it is resumed with goes in.
     */
    public function aDrivenScopeRelaysSuspensionValuesBothWays(): void
    {
        $local = new FiberLocal('base');

        $observed = DrivenScopes::run(static function () use ($local): array {
            $outer = new \Fiber(static fn(): mixed => $local->scope(
                'scoped',
                static function () use ($local): string {
                    $resumed = (string) \Fiber::suspend('parked');

                    return $resumed . '/' . $local->get();
                },
            ));

            $parkedWith = $outer->start();
            $outer->resume('resumed');

            return [$parkedWith, $outer->getReturn()];
        });

        Assert::same($observed, ['parked', 'resumed/scoped']);
    }

    public function aDrivenScopeRelaysAnInjectedThrowableToItsBody(): void
    {
        $local = new FiberLocal('base');

        $observed = DrivenScopes::run(static function () use ($local): mixed {
            $outer = new \Fiber(static fn(): mixed => $local->scope(
                'scoped',
                static function () use ($local): string {
                    try {
                        \Fiber::suspend();
                    } catch (\RuntimeException $e) {
                        // The scope is still in force while the body handles the throw.
                        return $e->getMessage() . '/' . $local->get();
                    }

                    return 'nothing was injected';
                },
            ));

            $outer->start();
            $outer->throw(new \RuntimeException('boom'));

            return $outer->getReturn();
        });

        Assert::same($observed, 'boom/scoped');
    }

    /**
     * The other half of the swap: while the body is parked it does not hold the floor, so whoever runs
     * meanwhile sees the enclosing value rather than the scope's.
     */
    public function aParkedDrivenScopeGivesTheFloorBack(): void
    {
        $local = new FiberLocal('base');

        $observed = DrivenScopes::run(static function () use ($local): array {
            $outer = new \Fiber(static fn(): mixed => $local->scope(
                'scoped',
                static fn(): mixed => \Fiber::suspend(),
            ));
            $outer->start();

            $whileParked = $local->get();

            $bystander = new \Fiber(static fn(): mixed => $local->get());
            $bystander->start();

            $outer->resume();

            return [$whileParked, $bystander->getReturn()];
        });

        Assert::same($observed, ['base', 'base']);
    }

    public function aDrivenScopeRunsDestroyAfterRestoring(): void
    {
        $local = new FiberLocal('base');
        $calls = 0;
        $seenDuringDestroy = 'unset';

        // Regular closures, not arrow functions: the latter capture by value, so the by-reference `use`
        // below would bind to their copies and never report back here.
        $returned = DrivenScopes::run(static function () use ($local, &$calls, &$seenDuringDestroy): mixed {
            $outer = new \Fiber(static function () use ($local, &$calls, &$seenDuringDestroy): mixed {
                return $local->scope(
                    'scoped',
                    static fn(): int => 1,
                    static function () use ($local, &$calls, &$seenDuringDestroy): void {
                        ++$calls;
                        $seenDuringDestroy = $local->get();
                    },
                );
            });
            $outer->start();

            return $outer->getReturn();
        });

        Assert::same($returned, 1);
        Assert::same($calls, 1);
        Assert::same($seenDuringDestroy, 'base');
    }

    public function aDrivenScopeRestoresTheEnclosingValueAfterItsBodyThrows(): void
    {
        $local = new FiberLocal('base');

        $observed = DrivenScopes::run(static function () use ($local): mixed {
            $outer = new \Fiber(static function () use ($local): string {
                try {
                    $local->scope(
                        'scoped',
                        static function (): never {
                            throw new \RuntimeException('boom');
                        },
                    );
                } catch (\RuntimeException) {
                    // Incidental machinery — what matters is the restore in the driver's finally.
                }

                return (string) $local->get();
            });
            $outer->start();

            return $outer->getReturn();
        });

        Assert::same($observed, 'base');
    }

    /**
     * Outside the region a scope is bound to its fiber as usual, so a fiber spawned by the body is out of
     * reach again — the region is what turns driving on, nothing else.
     */
    public function outsideTheRegionAScopeIsBoundNotDriven(): void
    {
        $local = new FiberLocal('base');

        $outer = new \Fiber(static fn(): mixed => $local->scope(
            'scoped',
            static function () use ($local): mixed {
                $sibling = new \Fiber(static fn(): mixed => $local->get());
                $sibling->start();

                // One bound fiber, so the unambiguous fallback still answers — with the scope's value,
                // but by inference rather than because the scope published it.
                return [$local->get(), $sibling->getReturn()];
            },
        ));
        $outer->start();

        Assert::same($outer->getReturn(), ['scoped', 'scoped']);
    }

    /**
     * A driven scope has to hand suspensions to somebody. Opened outside every fiber there is no such
     * somebody, and a body that parks would hang — so it says so instead.
     */
    public function aDrivenScopeOpenedOutsideAnyFiberRejectsAParkingBody(): never
    {
        $local = new FiberLocal('base');

        Expect::exception(\LogicException::class)->withMessageContaining('outside any fiber');

        DrivenScopes::run(
            static fn(): mixed => $local->scope('scoped', static fn(): mixed => \Fiber::suspend()),
        );
    }
}
