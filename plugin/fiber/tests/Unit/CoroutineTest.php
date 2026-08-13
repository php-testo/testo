<?php

declare(strict_types=1);

namespace Tests\Fiber\Unit;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Fiber\Coroutine;
use Testo\Fiber\Exception\CancelledException;
use Testo\Fiber\Exception\CompositeException;
use Testo\Fiber\Exception\DeadlockException;
use Testo\Fiber\Internal\Scheduler;
use Testo\Test;

/**
 * Unit checks for the {@see Coroutine} helpers on a hand-driven scheduler: each scenario spawns a
 * "body" task into a fresh scope and drives it, mirroring what {@see \Testo\Fiber\RunInFiber} does
 * for a real test.
 */
#[Test]
#[Covers(Coroutine::class)]
#[Covers(CompositeException::class)]
#[Covers(DeadlockException::class)]
final class CoroutineTest
{
    public function spawnOutsideAScopeThrows(): void
    {
        $caught = null;
        try {
            Coroutine::spawn(static fn() => null);
        } catch (\LogicException $e) {
            $caught = $e;
        }

        Assert::notNull($caught);
        Assert::string($caught->getMessage())->contains('RunInFiber');
    }

    public function awaitReturnsTheCoroutineResult(): void
    {
        $result = $this->scope(static function (): mixed {
            $sum = Coroutine::spawn(static function (): int {
                \Fiber::suspend();
                return 40 + 2;
            });

            return $sum->await();
        });

        Assert::same($result, 42);
    }

    public function awaitRethrowsWrappedInAComposite(): void
    {
        $boom = new \RuntimeException('boom');

        $caught = $this->scope(static function () use ($boom): mixed {
            $bad = Coroutine::spawn(static fn() => throw $boom);
            try {
                $bad->await();
            } catch (CompositeException $e) {
                return $e;
            }

            return null;
        });

        Assert::instanceOf($caught, CompositeException::class);
        Assert::same(\array_values($caught->errors), [$boom]);
        Assert::same($caught->getPrevious(), $boom);
    }

    public function awaitOnAFinishedCoroutineReturnsImmediately(): void
    {
        $log = [];
        $result = $this->scope(static function () use (&$log): mixed {
            $quick = Coroutine::spawn(static fn(): string => 'done');
            \Fiber::suspend();
            $log[] = 'body';

            return $quick->await();
        });

        Assert::same($result, 'done');
        Assert::same($log, ['body']);
    }

    public function selfAwaitThrows(): void
    {
        $caught = $this->scope(static function (): mixed {
            $handle = null;
            $inner = Coroutine::spawn(static function () use (&$handle): void {
                \Fiber::suspend();
                $handle->await();
            });
            $handle = $inner;
            try {
                return $inner->await();
            } catch (CompositeException $e) {
                return $e;
            }
        });

        Assert::instanceOf($caught, CompositeException::class);
        Assert::instanceOf($caught->getPrevious(), \LogicException::class);
    }

    public function concurrentlyReturnsResultsKeyedLikeTheArguments(): void
    {
        $results = $this->scope(static function (): array {
            return Coroutine::concurrently(
                first: static function (): string {
                    \Fiber::suspend();
                    return 'one';
                },
                second: static fn(): string => 'two',
            );
        });

        Assert::same($results, ['first' => 'one', 'second' => 'two']);
    }

    public function concurrentlyBundlesEveryFailureKeyedLikeTheArguments(): void
    {
        $first = new \RuntimeException('first broke');
        $pushError = new \LogicException('push broke');
        $log = [];

        $caught = $this->scope(static function () use ($first, $pushError, &$log): mixed {
            try {
                Coroutine::concurrently(
                    static fn() => throw $first,
                    ok: static fn(): string => 'fine',
                    push: static function () use (&$log, $pushError): void {
                        \Fiber::suspend();
                        $log[] = 'slow ran to its end';
                        throw $pushError;
                    },
                );
            } catch (CompositeException $e) {
                return $e;
            }

            return null;
        });

        Assert::instanceOf($caught, CompositeException::class);
        # Every coroutine settled before the bundle was thrown; errors are keyed like the arguments.
        Assert::same($caught->errors, [0 => $first, 'push' => $pushError]);
        Assert::same($caught->getPrevious(), $first);
        Assert::same($log, ['slow ran to its end']);
        # String keys name the fiber in the message as-is; int keys keep the #N form.
        Assert::string($caught->getMessage())->contains('push');
        Assert::string($caught->getMessage())->contains('#0');
    }

    public function awaitCycleIsBrokenAsADeadlock(): void
    {
        $caught = $this->scope(static function (): mixed {
            $a = null;
            $b = null;
            $a = Coroutine::spawn(static function () use (&$b): void {
                \Fiber::suspend();
                $b->await();
            });
            $b = Coroutine::spawn(static fn(): mixed => $a->await());

            try {
                return $a->await();
            } catch (DeadlockException $e) {
                return $e;
            }
        });

        # The first parked task (here: the body itself) gets the deadlock right at its await() call.
        Assert::instanceOf($caught, DeadlockException::class);
        Assert::string($caught->getMessage())->contains('await');
    }

    /**
     * A cancelled coroutine has no result to report: awaiting it from the teardown (a sibling's
     * `catch`/`finally` unwinding on the same cancellation) rethrows the cancellation instead of
     * forging a `null` result.
     */
    public function awaitOnACancelledCoroutineThrowsTheCancellation(): void
    {
        $observed = null;
        $scheduler = new Scheduler();
        $body = $scheduler->spawn(static function () use (&$observed): void {
            $victim = Coroutine::spawn(static fn(): mixed => \Fiber::suspend());
            Coroutine::spawn(static function () use ($victim, &$observed): void {
                try {
                    \Fiber::suspend();
                } catch (CancelledException) {
                    try {
                        $observed = $victim->await();
                    } catch (CancelledException $e) {
                        $observed = $e;
                    }
                }
            });

            \Fiber::suspend();
            throw new \RuntimeException('body died');
        });

        $scheduler->drive($body);

        Assert::instanceOf($observed, CancelledException::class);
    }

    public function unfinishedCoroutinesAreDrivenAfterTheBodyReturns(): void
    {
        $log = [];
        $this->scope(static function () use (&$log): void {
            Coroutine::spawn(static function () use (&$log): void {
                $log[] = 'child.1';
                \Fiber::suspend();
                $log[] = 'child.2';
            });
            $log[] = 'body done';
        });

        Assert::same($log, ['body done', 'child.1', 'child.2']);
    }

    /**
     * Run `$body` as the primary task of a fresh coroutine scope and return its result.
     */
    private function scope(\Closure $body): mixed
    {
        $scheduler = new Scheduler();
        $primary = $scheduler->spawn($body);
        $scheduler->drive($primary);

        $primary->error === null or throw $primary->error;

        return $primary->result;
    }
}
