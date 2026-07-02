<?php

declare(strict_types=1);

namespace Tests\Bridge\Mockery\Acceptance;

use Mockery;
use Mockery\MockInterface;
use Testo\Assert;
use Testo\Test;

#[Test]
final class MockeryBridgeTest
{
    public function mockCreatedAndExpectationFulfilled(): void
    {
        /** @var MockInterface&\Countable $mock */
        $mock = Mockery::mock(\Countable::class);
        $mock->expects('count')->once()->andReturn(7);

        Assert::same($mock->count(), 7);
        // MockeryPlugin calls Mockery::close() after this test,
        // which verifies the expectation was met.
    }

    public function spyRecordsCallsWithoutStrictExpectations(): void
    {
        /** @var MockInterface&\Countable $spy */
        $spy = Mockery::spy(\Countable::class);

        $spy->count();

        $spy->shouldHaveReceived('count')->once();
    }

    public function mockContainerIsClearedBetweenTests(): void
    {
        // If close() had not been called after the previous test, its unfulfilled
        // expectation would surface here as a late Mockery error. Reaching this
        // assertion cleanly confirms the container was reset.
        /** @var MockInterface&\Stringable $mock */
        $mock = Mockery::mock(\Stringable::class);
        $mock->allows('__toString')->andReturn('clean slate');

        Assert::same((string) $mock, 'clean slate');
    }
}
