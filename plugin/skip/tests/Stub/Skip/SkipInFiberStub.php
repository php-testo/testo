<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\Skip;

use Testo\Assert;
use Testo\Fiber\RunInFiber;
use Testo\Fiber\Schedule;
use Testo\Test;
use Testo\Skip;

/**
 * A class-level `#[RunInFiber]` ({@see Schedule::RoundRobin}) installs a fiber batch runner on the
 * case; the skip interceptor must wrap that runner, not replace it. The two enabled tests
 * suspend once each and write to a shared log: only the case scheduler produces the
 * round-robin interleaving `first.1, second.1, first.2, second.2` — run sequentially, the
 * `\Fiber::suspend()` outside a fiber would throw and the log would stop short.
 *
 * Driven through {@see \Testo\Testing\Helper\TestRunner} by the Feature suite;
 * {@see \Tests\Skip\Feature\SkipFeatureTest::fiberBatchRunnerSurvivesTheWrap()} asserts the
 * interleaving. The log accumulates across directory runs — this stub's tests and the feature
 * test assert the tail written by their own run.
 */
#[Test]
#[RunInFiber(Schedule::RoundRobin)]
final class SkipInFiberStub
{
    /** @var list<non-empty-string> */
    public static array $log = [];

    #[Skip('skipped inside a fiber-driven case')]
    public function skipped(): void
    {
        throw new \LogicException('Must never run: the test is skipped.');
    }

    public function first(): void
    {
        self::$log[] = 'first.1';
        \Fiber::suspend();
        self::$log[] = 'first.2';

        # Round-robin: after the yield, "second" has had its first step in between.
        Assert::same(\array_slice(self::$log, -3), ['first.1', 'second.1', 'first.2']);
    }

    public function second(): void
    {
        self::$log[] = 'second.1';
        \Fiber::suspend();
        self::$log[] = 'second.2';

        Assert::same(\array_slice(self::$log, -4), ['first.1', 'second.1', 'first.2', 'second.2']);
    }
}
