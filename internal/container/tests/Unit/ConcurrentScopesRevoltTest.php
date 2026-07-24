<?php

declare(strict_types=1);

namespace Internal\Container\Tests\Unit;

use Internal\Container\ObjectContainer;
use Internal\Container\Tests\Unit\Stub\ContainerScopeService;
use Revolt\EventLoop;
use Testo\Assert;
use Testo\Bridge\Revolt\RunInRevolt;
use Testo\Bridge\Revolt\Strategy;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeClass;
use Testo\Test;

/**
 * Container `scope()` isolation under **concurrent** tests on a real Revolt loop.
 *
 * `#[RunInRevolt(Strategy::PerCase)]` launches both tests at once; they share one {@see ObjectContainer},
 * each enters its own scope, sets a tag, awaits a real timer, and must still read its own scoped instance
 * once the loop resumes it — proving per-fiber container state holds while the other test is genuinely
 * interleaving at its await point.
 */
#[Test]
#[RunInRevolt(Strategy::PerCase)]
#[Covers(ObjectContainer::class)]
final class ConcurrentScopesRevoltTest
{
    private static ObjectContainer $container;

    #[BeforeClass]
    public static function bootContainer(): void
    {
        self::$container = new ObjectContainer();
    }

    public function firstScopeKeepsItsStateAcrossAConcurrentAwait(): void
    {
        self::$container->scope(static function (ObjectContainer $scoped): void {
            $scoped->get(ContainerScopeService::class)->tag = 1;

            $suspension = EventLoop::getSuspension();
            EventLoop::delay(0.001, static fn() => $suspension->resume());
            $suspension->suspend();

            Assert::same($scoped->get(ContainerScopeService::class)->tag, 1);
        });
    }

    public function secondScopeKeepsItsStateAcrossAConcurrentAwait(): void
    {
        self::$container->scope(static function (ObjectContainer $scoped): void {
            $scoped->get(ContainerScopeService::class)->tag = 2;

            $suspension = EventLoop::getSuspension();
            EventLoop::delay(0.001, static fn() => $suspension->resume());
            $suspension->suspend();

            Assert::same($scoped->get(ContainerScopeService::class)->tag, 2);
        });
    }
}
