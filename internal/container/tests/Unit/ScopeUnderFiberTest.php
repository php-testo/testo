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
 * Three tests share one {@see ObjectContainer} and run under `#[RunInFiber(Schedule::RoundRobin)]`, so they
 * interleave at each `\Fiber::suspend()`. Each enters its own scope on the shared container, sets a distinct
 * tag, then yields **repeatedly** — re-reading its scoped instance after every suspension. The tag must hold
 * across all of them, proving the container's per-fiber state survives many rounds of two other tests
 * running in between.
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
        self::scopeHoldsTagAcrossSuspends(1);
    }

    public function secondScopeSurvivesTheInterleave(): void
    {
        self::scopeHoldsTagAcrossSuspends(2);
    }

    public function thirdScopeSurvivesTheInterleave(): void
    {
        self::scopeHoldsTagAcrossSuspends(3);
    }

    /**
     * Enter a scope, tag its {@see ContainerScopeService}, then suspend three times, asserting after each
     * yield that the scoped instance still carries this test's own tag.
     */
    private static function scopeHoldsTagAcrossSuspends(int $tag): void
    {
        self::$container->scope(static function (ObjectContainer $scoped) use ($tag): void {
            $scoped->get(ContainerScopeService::class)->tag = $tag;

            for ($i = 0; $i < 3; $i++) {
                \Fiber::suspend();
                Assert::same($scoped->get(ContainerScopeService::class)->tag, $tag);
            }
        });
    }
}
