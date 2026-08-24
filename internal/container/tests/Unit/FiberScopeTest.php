<?php

declare(strict_types=1);

namespace Internal\Container\Tests\Unit;

use Internal\Container\ObjectContainer;
use Internal\Container\Tests\Testo\Fibers;
use Internal\Container\Tests\Unit\Stub\ContainerScopeService;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Filter\Group;
use Testo\Test;

/**
 * {@see ObjectContainer::scope()} driven by hand through plain fibers — exceptions injected at a
 * suspension point and many scopes interleaving on one thread.
 *
 * Adapted from Spiral's Core scope fiber tests. Assertions run on the test fiber: values are collected
 * or returned out of the driven fibers, keeping the hand-driven fibers free of framework calls.
 */
#[Test]
#[Group('async')]
#[Covers(ObjectContainer::class)]
final class FiberScopeTest
{
    public function scopeCatchesAnExceptionInjectedAtSuspendAndKeepsItsState(): void
    {
        $container = new ObjectContainer();

        $result = Fibers::runInFiber(
            static fn(): string => $container->scope(static function (ObjectContainer $scoped): string {
                $scoped->get(ContainerScopeService::class)->tag = 7;

                $out = (string) \Fiber::suspend('foo');
                try {
                    $out .= (string) \Fiber::suspend('error');
                } catch (\Throwable $e) {
                    $out .= $e->getMessage();
                }
                $out .= (string) \Fiber::suspend('baz');

                // The scoped instance survived the injected throw and the suspensions around it.
                return $out . '/' . $scoped->get(ContainerScopeService::class)->tag;
            }),
            static fn(string $value): string => $value === 'error'
                ? throw new \RuntimeException('X')
                : $value,
        );

        Assert::same($result, 'fooXbaz/7');
    }

    public function anUncaughtInjectedExceptionPropagatesOutOfTheScope(): never
    {
        $container = new ObjectContainer();

        Expect::exception(\RuntimeException::class)->withMessage('boom');

        Fibers::runInFiber(
            static fn(): mixed => $container->scope(static function (ObjectContainer $scoped): string {
                $scoped->get(ContainerScopeService::class);
                \Fiber::suspend('foo');
                return (string) \Fiber::suspend('error');
            }),
            static fn(string $value): string => $value === 'error'
                ? throw new \RuntimeException('boom')
                : $value,
        );
    }

    public function manyConcurrentFibersKeepScopedStateIsolated(): void
    {
        $root = new ObjectContainer();

        $scopeTagging = static fn(int $tag): callable => static function () use ($root, $tag): int {
            return $root->scope(static function (ObjectContainer $scoped) use ($tag): int {
                $scoped->get(ContainerScopeService::class)->tag = $tag;
                \Fiber::suspend();
                return $scoped->get(ContainerScopeService::class)->tag;
            });
        };

        $results = Fibers::runSequence(
            $scopeTagging(1),
            $scopeTagging(2),
            $scopeTagging(3),
            $scopeTagging(4),
            $scopeTagging(5),
        );

        Assert::same($results, [1, 2, 3, 4, 5]);
    }
}
