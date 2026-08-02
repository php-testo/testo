<?php

declare(strict_types=1);

namespace Internal\Container\Tests\Unit;

use Internal\Container\ObjectContainer;
use Internal\Container\Tests\Unit\Stub\ContainerScopeService;
use Internal\Fiber\DrivenScopes;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * Whole **trees** of hand-driven fibers, each rooted in its own {@see ObjectContainer::scope()}.
 *
 * A tree opens a scope, tags its scoped service, then spawns branch fibers which spawn leaf fibers, every
 * level driving its children in its own round-robin loop and yielding between rounds. Nothing below the
 * root opens a scope of its own: every fiber in the tree resolves through the container it inherited, so
 * each of them must see its own tree's tag — three trees alive at once must never read each other's
 * scoped instance, nor fall back to the root container.
 */
#[Test]
#[Covers(ObjectContainer::class)]
final class FiberTreeScopeTest
{
    /** Tag of each concurrently running tree; also the key its results come back under. */
    private const TAGS = [1, 2, 3];

    private const BRANCHES = 2;
    private const LEAVES = 2;

    /** Observations per tree: each branch reports its own tag plus one per leaf. */
    private const PER_TREE = self::BRANCHES * (1 + self::LEAVES);

    public function everyFiberOfATreeResolvesItsOwnTreesScope(): void
    {
        $root = new ObjectContainer();

        // Every fiber here is driven by hand, so the scopes may be driven too — which is what lets the
        // spawned fibers reach them at all.
        $observed = DrivenScopes::run(static function () use ($root): array {
            $trees = [];
            foreach (self::TAGS as $tag) {
                $trees[$tag] = self::tree($root, $tag);
            }

            return self::drive($trees, yieldBetweenRounds: false);
        });

        $expected = [];
        foreach (self::TAGS as $tag) {
            $expected[$tag] = \array_fill(0, self::PER_TREE, $tag);
        }

        Assert::same($observed, $expected);
    }

    /**
     * Control for the harness itself: with a single tree the very same machinery must resolve the scope at
     * every depth, so a failure above is about several scopes being alive at once, not about the driving.
     */
    public function aLoneTreeResolvesItsScopeAtEveryDepth(): void
    {
        $root = new ObjectContainer();

        $observed = DrivenScopes::run(
            static fn(): array => self::drive([7 => self::tree($root, 7)], yieldBetweenRounds: false),
        );

        Assert::same($observed, [7 => \array_fill(0, self::PER_TREE, 7)]);
    }

    /**
     * Root of one tree: opens the scope, tags it, and drives the branches.
     */
    private static function tree(ObjectContainer $root, int $tag): \Fiber
    {
        return new \Fiber(static fn(): array => $root->scope(
            static function (ObjectContainer $scoped) use ($tag): array {
                $scoped->get(ContainerScopeService::class)->tag = $tag;

                // Park with the scope open so the sibling trees open theirs too: everything below reads
                // while all the scopes are alive, which is the situation under test.
                \Fiber::suspend();

                $branches = [];
                for ($i = 0; $i < self::BRANCHES; ++$i) {
                    $branches[] = self::branch($scoped);
                }

                $observed = [];
                foreach (self::drive($branches, yieldBetweenRounds: true) as $reported) {
                    foreach ($reported as $tagSeen) {
                        $observed[] = $tagSeen;
                    }
                }

                return $observed;
            },
        ));
    }

    /**
     * Middle level: reports the tag it resolves, then drives its own leaves.
     */
    private static function branch(ObjectContainer $scoped): \Fiber
    {
        return new \Fiber(static function () use ($scoped): array {
            \Fiber::suspend();

            $observed = [self::resolveTag($scoped)];

            $leaves = [];
            for ($i = 0; $i < self::LEAVES; ++$i) {
                $leaves[] = self::leaf($scoped);
            }

            foreach (self::drive($leaves, yieldBetweenRounds: true) as $tagSeen) {
                $observed[] = $tagSeen;
            }

            return $observed;
        });
    }

    private static function leaf(ObjectContainer $scoped): \Fiber
    {
        return new \Fiber(static fn(): int => self::resolveTagAfterYield($scoped));
    }

    private static function resolveTagAfterYield(ObjectContainer $scoped): int
    {
        \Fiber::suspend();

        return self::resolveTag($scoped);
    }

    private static function resolveTag(ObjectContainer $scoped): int
    {
        return $scoped->get(ContainerScopeService::class)->tag;
    }

    /**
     * Start every fiber, then resume them round-robin until all have finished, optionally yielding between
     * rounds so our own siblings — and the other trees above us — interleave with this subtree.
     *
     * @param array<int, \Fiber> $fibers
     * @return array<int, mixed> Each fiber's return value under the key it was given.
     */
    private static function drive(array $fibers, bool $yieldBetweenRounds): array
    {
        $results = [];

        foreach ($fibers as $fiber) {
            $fiber->start();
        }

        while ($fibers !== []) {
            foreach ($fibers as $key => $fiber) {
                if ($fiber->isTerminated()) {
                    $results[$key] = $fiber->getReturn();
                    unset($fibers[$key]);
                    continue;
                }

                $fiber->resume();
            }

            $fibers !== [] and $yieldBetweenRounds and \Fiber::suspend();
        }

        \ksort($results);

        return $results;
    }
}
