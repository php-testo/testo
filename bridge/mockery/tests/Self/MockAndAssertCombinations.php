<?php

declare(strict_types=1);

namespace Tests\Bridge\Mockery\Self;

use Testo\Assert;
use Testo\Bridge\Mockery\Internal\MockeryInterceptor;
use Testo\Bridge\Mockery\MockeryPlugin;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

/**
 * Self tests: each method mixes Mockery expectations with Testo assertions and MUST finish Passed —
 * a Passed status is the proof that the bridge and the Assert plugin coexist.
 *
 * Negative cases (an unmet expectation must fail the test) live in the Feature suite: that failure
 * surfaces from `close()` after the method returns and cannot be observed with `Expect`.
 */
#[Test]
#[Covers(MockeryPlugin::class, MockeryInterceptor::class)]
final class MockAndAssertCombinations
{
    public function assertOnly(): void
    {
        Assert::same(1 + 1, 2);
    }

    public function mockExpectationOnly(): void
    {
        $mock = \Mockery::mock(\Countable::class);
        $mock->expects('count')->once()->andReturn(3);

        $mock->count();
    }

    public function mockThenAssert(): void
    {
        $mock = \Mockery::mock(\Countable::class);
        $mock->expects('count')->once()->andReturn(9);

        Assert::same($mock->count(), 9);
        Assert::instanceOf($mock, \Countable::class);
    }

    public function multipleMocksAndAsserts(): void
    {
        $counter = \Mockery::mock(\Countable::class);
        $counter->expects('count')->twice()->andReturn(1);
        $text = \Mockery::mock(\Stringable::class);
        $text->expects('__toString')->once()->andReturn('x');

        Assert::same($counter->count(), 1);
        $counter->count();
        Assert::same((string) $text, 'x');
    }

    public function spyWithAssert(): void
    {
        $spy = \Mockery::spy(\Countable::class);
        $spy->allows('count')->andReturn(7);

        Assert::same($spy->count(), 7);

        $spy->shouldHaveReceived('count')->once();
    }

    public function looseMockWithAssert(): void
    {
        $mock = \Mockery::mock(\Countable::class);
        $mock->allows('count')->andReturn(0);

        Assert::same($mock->count(), 0);
    }

    public function mockThrowsWithExpectException(): void
    {
        $mock = \Mockery::mock(\Countable::class);
        $mock->expects('count')->once()->andThrow(new \RuntimeException('boom'));

        Expect::exception(\RuntimeException::class)->withMessageContaining('boom');
        $mock->count();
    }
}
