<?php

declare(strict_types=1);

namespace Tests\Bridge\Double\Acceptance;

use JMac\Testing\Double;
use JMac\Testing\DoubleInterface;
use Testo\Assert;
use Testo\Bridge\Double\DoublePlugin;
use Testo\Bridge\Double\Internal\DoubleInterceptor;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * Acceptance tests for {@see DoublePlugin}. The suite registers the plugin
 * (see `bridge/double/tests/suites.php`), so `Double::verifyAll()` fires after
 * every test. Assertions therefore depend on the plugin doing its job:
 * expectations are verified on teardown with no per-test `verify()` call.
 */
#[Test]
#[Covers(DoublePlugin::class)]
#[Covers(DoubleInterceptor::class)]
final class DoubleBridgeTest
{
    public function doubleCreatedAndExpectationFulfilled(): void
    {
        /** @var DoubleInterface&\Countable $double */
        $double = Double::for(\Countable::class);
        $double->expects('count')->returns(7);

        Assert::same($double->count(), 7);
    }

    public function expectedCallCountIsVerifiedOnTeardown(): void
    {
        /** @var DoubleInterface&\Countable $double */
        $double = Double::for(\Countable::class);
        $double->expects('count')->times(2)->returns(2);

        $double->count();
        $double->count();
    }

    public function spyRecordsCallsWithReceived(): void
    {
        /** @var DoubleInterface&\Countable $spy */
        $spy = Double::for(\Countable::class);
        $spy->allows('count')->returns(3);

        Assert::same($spy->count(), 3);

        $spy->received('count')->times(1);
    }
}
