<?php

declare(strict_types=1);

namespace Tests\Bridge\Double\Self;

use JMac\Testing\Double;
use JMac\Testing\Exceptions\ExpectationCallLimitExceededException;
use JMac\Testing\Exceptions\UnexpectedCallException;
use JMac\Testing\Exceptions\UnusedAssertionException;
use Testo\Assert;
use Testo\Assert\ExpectException;
use Testo\Assert\Internal\StaticState;
use Testo\Bridge\Double\Internal\DoubleInterceptor;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

/**
 * Self tests: each method mixes Double expectations with Testo assertions and MUST finish Passed —
 * a Passed status is the proof that the bridge and the Assert plugin coexist.
 *
 * Negative cases (an unmet expectation must fail the test) live in the Feature suite: that failure
 * surfaces from `verifyAll()` after the method returns and cannot be observed with `Expect`.
 */
#[Test]
#[Covers(DoubleInterceptor::class)]
final class DoubleAndAssertCombinations
{
    public function assertOnly(): void
    {
        Assert::same(1 + 1, 2);
    }

    public function expectationOnly(): void
    {
        $double = Double::for(\Countable::class);
        $double->expects('count')->returns(3);

        $double->count();
    }

    public function expectationThenAssert(): void
    {
        $double = Double::for(\Countable::class);
        $double->expects('count')->returns(9);

        Assert::same($double->count(), 9);
        Assert::instanceOf($double, \Countable::class);
    }

    public function multipleDoublesAndAsserts(): void
    {
        $counter = Double::for(\Countable::class);
        $counter->expects('count')->times(2)->returns(1);
        $other = Double::for(\Countable::class);
        $other->expects('count')->returns(4);

        Assert::same($counter->count(), 1);
        $counter->count();
        Assert::same($other->count(), 4);
    }

    public function spyWithAssert(): void
    {
        $spy = Double::for(\Countable::class);
        $spy->allows('count')->returns(7);

        Assert::same($spy->count(), 7);

        $spy->received('count')->times(1);
    }

    public function checkResolvingWithNoActiveStateIsDropped(): void
    {
        $spy = Double::for(\Countable::class);
        $spy->allows('count')->returns(0);

        $state = StaticState::current();
        Assert::notNull($state);
        $before = \count($state->history);

        // The listener is process-wide, so a check can resolve with no test's collector swapped in
        // (a coroutine relayed out, another suite tearing down). It must be dropped, not appended to a
        // stale collector nor dereference the null. unused() resolves its CheckEvent synchronously.
        $restore = StaticState::swap(null);
        try {
            $spy->unused();
        } finally {
            StaticState::swap($restore);
        }

        Assert::same(\count($state->history), $before);
    }

    public function looseStubWithAssert(): void
    {
        $stub = Double::for(\Countable::class);
        $stub->allows('count')->returns(0);

        Assert::same($stub->count(), 0);
    }

    public function doubleThrowsWithExpectException(): void
    {
        $double = Double::for(\Countable::class);
        $double->expects('count')->throws(new \RuntimeException('boom'));

        Expect::exception(\RuntimeException::class)->withMessageContaining('boom');
        $double->count();
    }

    #[ExpectException(UnusedAssertionException::class)]
    public function unusedAssertionThrowsAndIsCaught(): void
    {
        $double = Double::for(\Countable::class);
        $double->allows('count')->returns(0);
        $double->count();

        // A check failing in the body throws at the call site, so #[ExpectException] sees it.
        $double->unused();
    }

    #[ExpectException(UnexpectedCallException::class)]
    public function strictUnexpectedCallThrowsAndIsCaught(): void
    {
        $double = Double::for(\Countable::class)->strict();

        $double->count();
    }

    #[ExpectException(ExpectationCallLimitExceededException::class)]
    public function neverExpectationExceededThrowsAndIsCaught(): void
    {
        $double = Double::for(\Countable::class);
        $double->expects('count')->never();

        $double->count();
    }

    #[ExpectException(ExpectationCallLimitExceededException::class)]
    public function callCountExceededThrowsAndIsCaught(): void
    {
        $double = Double::for(\Countable::class);
        $double->expects('count')->times(1)->returns(0);

        $double->count();
        $double->count();
    }
}
