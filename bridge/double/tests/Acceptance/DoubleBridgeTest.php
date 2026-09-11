<?php

declare(strict_types=1);

namespace Tests\Bridge\Double\Acceptance;

use JMac\Testing\Double;
use JMac\Testing\DoubleInterface;
use JMac\Testing\Matching\Argument;
use Testo\Assert;
use Testo\Bridge\Double\DoublePlugin;
use Testo\Codecov\CoversNothing;
use Testo\Test;
use Tests\Bridge\Double\Fixture\Adder;
use Tests\Bridge\Double\Fixture\Greeter;
use Tests\Bridge\Double\Fixture\Permissions;

/**
 * Acceptance tests for {@see DoublePlugin}. The suite registers the plugin
 * (see `bridge/double/tests/suites.php`), so `Double::verifyAll()` fires after
 * every test. Assertions therefore depend on the plugin doing its job:
 * expectations are verified on teardown with no per-test `verify()` call.
 */
#[Test]
#[CoversNothing]
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

    public function jointArgumentMatchingWithArgumentAll(): void
    {
        // Argument::all() weighs the whole argument list at once: the call matches
        // only because 2 < 7. The plugin verifies the expectation on teardown.
        /** @var DoubleInterface&Adder $double */
        $double = Double::for(Adder::class);
        $double->expects('add')->with(Argument::all(fn(int $a, int $b): bool => $a < $b))->returns(9);

        Assert::same($double->add(2, 7), 9);
    }

    public function overrideDoublesATargetWithAReservedNameCollision(): void
    {
        // Permissions::allows() collides with a Double control verb; override: true
        // hands back an OverriddenDouble carrying the verbs, instance() the target-shaped double.
        $permissions = Double::for(Permissions::class, override: true);
        $permissions->expects('allows')->with('edit')->returns(true);

        Assert::true($permissions->instance()->allows('edit'));
    }

    public function passthruSelfCallReachesAStub(): void
    {
        // greet()'s real body runs and its $this->normalize() self-call re-enters
        // the double, so the stubbed normalize() answers instead of the real one.
        /** @var DoubleInterface&Greeter $greeter */
        $greeter = Double::for(Greeter::class);
        $greeter->passthru();
        $greeter->allows('normalize')->returns('WORLD');

        Assert::same($greeter->greet('world'), 'Hello, WORLD');
    }

    public function aClonedDoubleSharesStateWithItsOriginal(): void
    {
        // The clone resolves to the same expectation state, so calling count() on it
        // fulfills the expectation set on the original.
        /** @var DoubleInterface&\Countable $double */
        $double = Double::for(\Countable::class);
        $double->expects('count')->returns(3);

        $clone = clone $double;

        Assert::same($clone->count(), 3);
    }
}
