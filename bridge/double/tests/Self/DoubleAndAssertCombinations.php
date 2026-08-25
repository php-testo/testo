<?php

declare(strict_types=1);

namespace Tests\Bridge\Double\Self;

use JMac\Testing\Double;
use JMac\Testing\Exceptions\ExpectationCallLimitExceededException;
use JMac\Testing\Exceptions\UnexpectedCallException;
use JMac\Testing\Exceptions\UnusedAssertionException;
use Testo\Assert;
use Testo\Assert\ExpectException;
use Testo\Bridge\Double\DoublePlugin;
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
#[Covers(DoublePlugin::class)]
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

        // A Double check that fails in the test body (here: unused() on a called double) throws like any
        // other exception, so #[ExpectException] catches it. Unlike an unmet expects(), which surfaces only
        // from the teardown verifyAll() and leaves nothing for the attribute to see.
        $double->unused();
    }

    #[ExpectException(UnexpectedCallException::class)]
    public function strictUnexpectedCallThrowsAndIsCaught(): void
    {
        // A strict double rejects any call it was not configured for, right at the call site.
        $double = Double::for(\Countable::class)->strict();

        $double->count();
    }

    #[ExpectException(ExpectationCallLimitExceededException::class)]
    public function neverExpectationExceededThrowsAndIsCaught(): void
    {
        // never() allows zero calls; the first call breaks the limit at the call site.
        $double = Double::for(\Countable::class);
        $double->expects('count')->never();

        $double->count();
    }

    #[ExpectException(ExpectationCallLimitExceededException::class)]
    public function callCountExceededThrowsAndIsCaught(): void
    {
        // times(1) allows a single call; the second call exceeds the limit at the call site.
        $double = Double::for(\Countable::class);
        $double->expects('count')->times(1)->returns(0);

        $double->count();
        $double->count();
    }
}
