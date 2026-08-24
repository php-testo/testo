<?php

declare(strict_types=1);

namespace Internal\Container\Tests\Unit;

use Internal\Container\ObjectContainer;
use Internal\Container\Tests\Unit\Stub\ContainerScopeService;
use Internal\Container\Tests\Unit\Stub\Greeter;
use Internal\Container\Tests\Unit\Stub\GreeterInterface;
use Internal\Container\Tests\Unit\Stub\ReadonlyTag;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Filter\Group;
use Testo\Test;

/**
 * {@see ObjectContainer::scope()} isolation — synchronously and across interleaved fibers.
 *
 * A scope clones the active state, so its resolved services are its own; inside a fiber, `scope()` swaps
 * the parent state back in at every suspension, so two fibers each in their own scope never observe each
 * other's instances even while interleaving on one thread.
 */
#[Test]
#[Covers(ObjectContainer::class)]
final class ScopeIsolationTest
{
    public function syncScopeIsolatesFromParent(): void
    {
        $container = new ObjectContainer();
        $container->get(ContainerScopeService::class)->tag = 10;

        $inside = $container->scope(static function (ObjectContainer $scoped): int {
            $service = $scoped->get(ContainerScopeService::class);
            $service->tag = 99;
            return $service->tag;
        });

        Assert::same($inside, 99);
        Assert::same($container->get(ContainerScopeService::class)->tag, 10);
    }

    public function nestedSyncScopesRestoreTheParent(): void
    {
        $container = new ObjectContainer();
        $container->get(ContainerScopeService::class)->tag = 1;

        $container->scope(static function (ObjectContainer $outer): void {
            $outer->get(ContainerScopeService::class)->tag = 2;

            $outer->scope(static function (ObjectContainer $inner): void {
                $inner->get(ContainerScopeService::class)->tag = 3;
            });

            Assert::same($outer->get(ContainerScopeService::class)->tag, 2);
        });

        Assert::same($container->get(ContainerScopeService::class)->tag, 1);
    }

    public function scopeReturnsTheClosureResult(): void
    {
        $container = new ObjectContainer();

        $result = $container->scope(static fn(): string => 'result');

        Assert::same($result, 'result');
    }

    public function scopeInheritsAClonedCopyOfParentCachedServices(): void
    {
        $container = new ObjectContainer();
        $parent = $container->get(ContainerScopeService::class);
        $parent->tag = 7;

        $container->scope(static function (ObjectContainer $scoped) use ($parent): void {
            $inScope = $scoped->get(ContainerScopeService::class);
            Assert::notSame($inScope, $parent);
            Assert::same($inScope->tag, 7);
            $inScope->tag = 99;
        });

        Assert::same($parent->tag, 7);
    }

    public function scopeSharesReadonlyServicesWithTheParent(): void
    {
        $container = new ObjectContainer();
        $parent = $container->get(ReadonlyTag::class);

        $inScope = $container->scope(static fn(ObjectContainer $scoped): ReadonlyTag => $scoped->get(ReadonlyTag::class));

        Assert::same($inScope, $parent);
    }

    public function scopeInheritsParentBindings(): void
    {
        $container = new ObjectContainer();
        $container->bind(GreeterInterface::class, Greeter::class);

        $inScope = $container->scope(
            static fn(ObjectContainer $scoped): GreeterInterface => $scoped->get(GreeterInterface::class),
        );

        Assert::instanceOf($inScope, Greeter::class);
    }

    public function bindingInsideAScopeDoesNotLeakToTheParent(): void
    {
        $container = new ObjectContainer();

        $container->scope(static function (ObjectContainer $scoped): void {
            $scoped->bind(GreeterInterface::class, Greeter::class);
            Assert::true($scoped->has(GreeterInterface::class));
        });

        Assert::false($container->has(GreeterInterface::class));
    }

    public function scopeRestoresTheParentAfterAnException(): void
    {
        $container = new ObjectContainer();
        $container->get(ContainerScopeService::class)->tag = 5;

        try {
            $container->scope(static function (ObjectContainer $scoped): never {
                $scoped->get(ContainerScopeService::class)->tag = 42;
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
            // The throw is incidental — what we verify is that the parent scope is intact afterwards.
        }

        Assert::same($container->get(ContainerScopeService::class)->tag, 5);
    }

    public function deeplyNestedScopesEachRestoreTheirParent(): void
    {
        $container = new ObjectContainer();
        $container->get(ContainerScopeService::class)->tag = 0;

        $deepest = $container->scope(static function (ObjectContainer $l1): int {
            $l1->get(ContainerScopeService::class)->tag = 1;

            $inner = $l1->scope(static function (ObjectContainer $l2): int {
                $l2->get(ContainerScopeService::class)->tag = 2;

                $l3 = $l2->scope(static fn(ObjectContainer $c): int => $c->get(ContainerScopeService::class)->tag = 3);

                Assert::same($l2->get(ContainerScopeService::class)->tag, 2);
                return $l3;
            });

            Assert::same($l1->get(ContainerScopeService::class)->tag, 1);
            return $inner;
        });

        Assert::same($deepest, 3);
        Assert::same($container->get(ContainerScopeService::class)->tag, 0);
    }

    /**
     * Two fibers each enter their own scope, park, then resume: each must still resolve its own scoped
     * instance across the switch — the isolation invariant a real event loop relies on.
     */
    #[Group('async')]
    public function interleavedScopesKeepSeparateInstances(): void
    {
        $container = new ObjectContainer();
        $seen = [];

        $fiberA = new \Fiber(static function () use ($container, &$seen): void {
            $container->scope(static function (ObjectContainer $scoped) use (&$seen): void {
                $scoped->get(ContainerScopeService::class)->tag = 1;
                \Fiber::suspend();
                $seen['A'] = $scoped->get(ContainerScopeService::class)->tag;
            });
        });
        $fiberB = new \Fiber(static function () use ($container, &$seen): void {
            $container->scope(static function (ObjectContainer $scoped) use (&$seen): void {
                $scoped->get(ContainerScopeService::class)->tag = 2;
                \Fiber::suspend();
                $seen['B'] = $scoped->get(ContainerScopeService::class)->tag;
            });
        });

        $fiberA->start();
        $fiberB->start();
        $fiberA->resume();
        $fiberB->resume();

        Assert::same($seen['A'], 1);
        Assert::same($seen['B'], 2);
    }
}
