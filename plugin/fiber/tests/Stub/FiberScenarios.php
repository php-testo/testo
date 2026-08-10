<?php

declare(strict_types=1);

namespace Tests\Fiber\Stub;

use Testo\Assert;
use Testo\Assert\ExpectException;
use Testo\Fiber\Coroutine;
use Testo\Fiber\Exception\CancelledException;
use Testo\Fiber\Exception\CompositeException;
use Testo\Fiber\RunInFiber;
use Testo\Test;

/**
 * Scenarios exercised end-to-end by {@see \Tests\Fiber\Feature\StatusTest} to assert how the plugin
 * maps onto Testo statuses.
 */
#[Test]
final class FiberScenarios
{
    /** @var list<string> What a pending coroutine observed when its test's body failed. */
    public static array $cancellationLog = [];

    #[RunInFiber]
    public function runsInAFiber(): void
    {
        Assert::notNull(\Fiber::getCurrent());
    }

    #[RunInFiber]
    public function failureInsideFiberPropagates(): void
    {
        Assert::same(1, 2);
    }

    public function untaggedRunsOnMainFiber(): void
    {
        Assert::null(\Fiber::getCurrent());
    }

    #[RunInFiber]
    public function unawaitedCoroutineFailure(): void
    {
        Coroutine::spawn(static fn() => throw new \RuntimeException('nobody awaited me'));
        Assert::true(true);
    }

    #[RunInFiber]
    public function awaitedCoroutineFailureHandledInTest(): void
    {
        $bad = Coroutine::spawn(static fn() => throw new \RuntimeException('boom'));
        try {
            $bad->await();
            Assert::true(false);
        } catch (CompositeException $e) {
            Assert::instanceOf($e->getPrevious(), \RuntimeException::class);
        }
    }

    #[RunInFiber]
    #[ExpectException(\DomainException::class)]
    public function bodyThrowStaysUnwrapped(): void
    {
        Coroutine::spawn(static fn(): string => 'fine');

        throw new \DomainException('straight from the body');
    }

    #[RunInFiber]
    public function assertionsInsideCoroutinesCountForTheTest(): void
    {
        Coroutine::spawn(static function (): void {
            Assert::true(true);
            \Fiber::suspend();
            Assert::true(true);
        })->await();

        Assert::true(true);
    }

    public function spawnWithoutFiberScope(): void
    {
        Coroutine::spawn(static fn(): string => 'no scope for me');
    }

    #[RunInFiber]
    public function failingBodyLeavesAPendingCoroutine(): void
    {
        Coroutine::spawn(static function (): void {
            try {
                \Fiber::suspend();
                self::$cancellationLog[] = 'survived';
            } catch (CancelledException) {
                self::$cancellationLog[] = 'cancelled';
            }
        });

        // Let the coroutine reach its suspension point before the body fails.
        \Fiber::suspend();

        Assert::same(1, 2);
    }
}
