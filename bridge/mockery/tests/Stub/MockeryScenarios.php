<?php

declare(strict_types=1);

namespace Tests\Bridge\Mockery\Stub;

use Mockery\MockInterface;
use Testo\Assert;
use Testo\Test;

/**
 * Stub tests exercised through {@see \Testo\Testing\Helper\TestRunner} by the Feature suite.
 * They are not discovered directly (the Stub directory is not a suite location); each method is
 * a scenario whose reported {@see \Testo\Core\Value\Status} the Feature test asserts on.
 */
final class MockeryScenarios
{
    #[Test]
    public function fulfilledExpectationOnly(): void
    {
        /** @var MockInterface&\Countable $mock */
        $mock = \Mockery::mock(\Countable::class);
        $mock->expects('count')->once()->andReturn(1);

        $mock->count();
    }

    #[Test]
    public function unfulfilledExpectation(): void
    {
        /** @var MockInterface&\Countable $mock */
        $mock = \Mockery::mock(\Countable::class);
        $mock->expects('count')->once();
    }

    #[Test]
    public function noMocksNoAssertions(): void {}

    #[Test]
    public function spyVerificationOnly(): void
    {
        /** @var MockInterface&\Countable $spy */
        $spy = \Mockery::spy(\Countable::class);

        $spy->count();

        $spy->shouldHaveReceived('count')->once();
    }

    #[Test]
    public function mockAndAssertMixed(): void
    {
        /** @var MockInterface&\Countable $mock */
        $mock = \Mockery::mock(\Countable::class);
        $mock->expects('count')->once()->andReturn(5);

        Assert::same($mock->count(), 5);
    }
}
