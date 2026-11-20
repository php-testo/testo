<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Rendering;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Output\Rendering\NumberFormat;
use Testo\Test;

#[Test]
#[Covers(NumberFormat::class)]
final class NumberFormatTest
{
    public function fixedKeepsAnIntegerExact(): void
    {
        Assert::same(NumberFormat::fixed(20), '20');
        Assert::same(NumberFormat::fixed(0), '0');
        Assert::same(NumberFormat::fixed(-7), '-7');
    }

    public function fixedTrimsTrailingZerosOfAFloat(): void
    {
        Assert::same(NumberFormat::fixed(5.1), '5.1');
        Assert::same(NumberFormat::fixed(0.25), '0.25');
        // A whole-valued float loses its fractional part rather than reading as `3.000000`.
        Assert::same(NumberFormat::fixed(3.0), '3');
    }

    public function fixedWritesANearZeroFloatInFixedNotation(): void
    {
        // Fixed notation, not `1.0E-6`, which a JUnit consumer or a TeamCity `type='number'` would have
        // to special-case.
        Assert::same(NumberFormat::fixed(0.000001), '0.000001');
    }
}
