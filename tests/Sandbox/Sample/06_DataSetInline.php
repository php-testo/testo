<?php

declare(strict_types=1);

/**
 * Slide 6. A handful of fixed cases inline: PHPUnit `#[TestWith]` (case name since 11.5) vs Testo `#[DataSet]`.
 * The shapes are on par here; the difference shows up in slides 7 and 8.
 */

namespace Sample\PhpUnit {

    use App\Pricing\Discount;
    use PHPUnit\Framework\Attributes\TestWith;
    use PHPUnit\Framework\TestCase;

    final class DiscountTest extends TestCase
    {
        #[TestWith([4_999, 4_999], 'below threshold')]
        #[TestWith([5_000, 4_500], 'exactly threshold')]
        #[TestWith([20_000, 17_000], 'silver tier')]
        #[TestWith([100_000, 80_000], 'gold tier')]
        public function testAppliesTieredDiscount(int $subtotal, int $expected): void
        {
            $this->assertSame($expected, Discount::apply($subtotal));
        }
    }
}

namespace Sample\Testo {

    use App\Pricing\Discount;
    use Testo\Assert;
    use Testo\Data\DataSet;
    use Testo\Test;

    #[Test]
    final class DiscountTest
    {
        #[DataSet([4_999, 4_999], 'below threshold')]
        #[DataSet([5_000, 4_500], 'exactly threshold')]
        #[DataSet([20_000, 17_000], 'silver tier')]
        #[DataSet([100_000, 80_000], 'gold tier')]
        public function appliesTieredDiscount(int $subtotal, int $expected): void
        {
            Assert::same(Discount::apply($subtotal), $expected);
        }
    }
}
