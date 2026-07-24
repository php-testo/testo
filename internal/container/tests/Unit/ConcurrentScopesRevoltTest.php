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
 * `#[RunInRevolt(Strategy::PerCase)]` launches all three tests at once; they share one {@see ObjectContainer},
 * each enters its own scope, sets a distinct tag, then awaits a real timer **repeatedly** — re-reading its
 * scoped instance after every await. The tag must hold across all of them while the other two tests are
 * genuinely interleaving at their own await points, proving per-fiber container state survives real
 * event-loop scheduling.
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

    public function firstScopeKeepsItsStateAcrossConcurrentAwaits(): void
    {
        self::scopeHoldsTagAcrossAwaits(1);
    }

    public function secondScopeKeepsItsStateAcrossConcurrentAwaits(): void
    {
        self::scopeHoldsTagAcrossAwaits(2);
    }

    public function thirdScopeKeepsItsStateAcrossConcurrentAwaits(): void
    {
        self::scopeHoldsTagAcrossAwaits(3);
    }

    /**
     * Enter a scope, tag its {@see ContainerScopeService}, then await a real timer three times, asserting
     * after each await that the scoped instance still carries this test's own tag.
     */
    private static function scopeHoldsTagAcrossAwaits(int $tag): void
    {
        self::$container->scope(static function (ObjectContainer $scoped) use ($tag): void {
            $scoped->get(ContainerScopeService::class)->tag = $tag;

            for ($i = 0; $i < 3; $i++) {
                $suspension = EventLoop::getSuspension();
                EventLoop::delay(0.001, static fn() => $suspension->resume());
                $suspension->suspend();

                Assert::same($scoped->get(ContainerScopeService::class)->tag, $tag);
            }
        });
    }
}
