<?php

declare(strict_types=1);

namespace Tests\Bridge\Double\Self;

use JMac\Testing\Double;
use Testo\Assert;
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
}
