<?php

declare(strict_types=1);

namespace Tests\Bridge\Double\Stub;

use JMac\Testing\Double;
use JMac\Testing\DoubleInterface;
use Testo\Assert;
use Testo\Test;

/**
 * Stub tests exercised through {@see \Testo\Testing\Helper\TestRunner} by the Feature suite.
 * They are not discovered directly (the Stub directory is not a suite location); each method is
 * a scenario whose reported {@see \Testo\Core\Value\Status} the Feature test asserts on.
 */
final class DoubleScenarios
{
    #[Test]
    public function fulfilledExpectationOnly(): void
    {
        /** @var DoubleInterface&\Countable $double */
        $double = Double::for(\Countable::class);
        $double->expects('count')->returns(1);

        $double->count();
    }

    #[Test]
    public function unfulfilledExpectation(): void
    {
        /** @var DoubleInterface&\Countable $double */
        $double = Double::for(\Countable::class);
        $double->expects('count');
    }

    #[Test]
    public function noDoublesNoAssertions(): void {}

    #[Test]
    public function receivedVerificationOnly(): void
    {
        /** @var DoubleInterface&\Countable $spy */
        $spy = Double::for(\Countable::class);
        $spy->allows('count')->returns(0);

        $spy->count();

        $spy->received('count')->times(1);
    }

    #[Test]
    public function doubleAndAssertMixed(): void
    {
        /** @var DoubleInterface&\Countable $double */
        $double = Double::for(\Countable::class);
        $double->expects('count')->returns(5);

        Assert::same($double->count(), 5);
    }

    #[Test]
    public function bodyCheckFailsUncaught(): void
    {
        /** @var DoubleInterface&\Countable $spy */
        $spy = Double::for(\Countable::class);
        $spy->allows('count')->returns(0);
        $spy->count();

        $spy->unused();
    }

    #[Test]
    public function checkInterleavesWithAssertions(): void
    {
        /** @var DoubleInterface&\Countable $spy */
        $spy = Double::for(\Countable::class);

        Assert::same(1, 1);
        $spy->unused(); // passes immediately (never called), recorded here rather than at teardown
        Assert::same(2, 2);
    }
}
