<?php

declare(strict_types=1);

namespace Tests\Bridge\Mockery\Acceptance;

use Mockery;
use Mockery\MockInterface;
use Testo\Assert;
use Testo\Bridge\Mockery\Internal\MockeryInterceptor;
use Testo\Bridge\Mockery\MockeryPlugin;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * Acceptance tests for {@see MockeryPlugin}. The suite registers the plugin
 * (see `bridge/mockery/tests/suites.php`), so `\Mockery::close()` fires after
 * every test. Assertions therefore depend on the plugin doing its job:
 * expectations are verified on teardown and the container is reset between tests.
 */
#[Test]
#[Covers(MockeryPlugin::class, MockeryInterceptor::class)]
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
        $spy->allows('count')->andReturn(3);

        Assert::same($spy->count(), 3);

        $spy->shouldHaveReceived('count')->once();
    }

    public function mockContainerIsClearedBetweenTests(): void
    {
        // The previous tests registered expectations on their mocks. If the plugin
        // had not called Mockery::close() after each of them, those expectations
        // would still live in the container now. A zero count proves it was reset.
        Assert::same(Mockery::getContainer()->mockery_getExpectationCount(), 0);

        /** @var MockInterface&\Stringable $mock */
        $mock = Mockery::mock(\Stringable::class);
        $mock->allows('__toString')->andReturn('clean slate');

        Assert::same((string) $mock, 'clean slate');
    }
}
