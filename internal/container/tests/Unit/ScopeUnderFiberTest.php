<?php

declare(strict_types=1);

namespace Internal\Container\Tests\Unit;

use Internal\Container\ObjectContainer;
use Internal\Container\Tests\Unit\Stub\ContainerScopeService;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Fiber\RunInFiber;
use Testo\Fiber\Schedule;
use Testo\Lifecycle\BeforeClass;
use Testo\Test;

/**
 * Container `scope()` isolation through the real pipeline on Testo's cooperative fiber scheduler.
 *
 * Both tests share one {@see ObjectContainer} and run under `#[RunInFiber(Schedule::RoundRobin)]`, so they
 * interleave at each `\Fiber::suspend()`. Each enters its own scope on the shared container, sets a tag,
 * yields, and must still read its own scoped instance afterwards — proving the container's per-fiber state
 * survives another test running in between.
 */
#[Test]
#[RunInFiber(Schedule::RoundRobin)]
#[Covers(ObjectContainer::class)]
final class ScopeUnderFiberTest
{
    private static ObjectContainer $container;

    #[BeforeClass]
    public static function bootContainer(): void
    {
        self::$container = new ObjectContainer();
    }

    public function firstScopeSurvivesTheInterleave(): void
    {
        self::$container->scope(static function (ObjectContainer $scoped): void {
            $scoped->get(ContainerScopeService::class)->tag = 1;
            \Fiber::suspend();
            Assert::same($scoped->get(ContainerScopeService::class)->tag, 1);
        });
    }

    public function secondScopeSurvivesTheInterleave(): void
    {
        self::$container->scope(static function (ObjectContainer $scoped): void {
            $scoped->get(ContainerScopeService::class)->tag = 2;
            \Fiber::suspend();
            Assert::same($scoped->get(ContainerScopeService::class)->tag, 2);
        });
    }
}
