<?php

declare(strict_types=1);

/**
 * Slide 1. Checking a domain object: typed assertion chains vs a wall of `$this->assert*`.
 */

namespace Sample\PhpUnit {

    use App\Checkout\Order;
    use PHPUnit\Framework\TestCase;

    final class OrderTest extends TestCase
    {
        public function testPlacesOrder(): void
        {
            $order = Order::place(customerId: 42, items: ['sku-1' => 2, 'sku-2' => 1]);

            $this->assertIsString($order->number);
            $this->assertStringStartsWith('ORD-', $order->number);
            $this->assertStringNotContainsString(' ', $order->number);

            $this->assertIsInt($order->total);
            $this->assertGreaterThan(0, $order->total);
            $this->assertLessThanOrEqual(1_000_000, $order->total);

            $this->assertIsArray($order->lines);
            $this->assertCount(2, $order->lines);
            $this->assertArrayHasKey('sku-1', $order->lines);
            $this->assertArrayHasKey('sku-2', $order->lines);
            $this->assertArrayNotHasKey('sku-3', $order->lines);
            $this->assertContainsOnlyInstancesOf(Order\Line::class, $order->lines);

            $this->assertSame(42, $order->customerId);
            $this->assertNull($order->paidAt);
        }
    }
}

namespace Sample\Testo {

    use App\Checkout\Order;
    use Testo\Assert;
    use Testo\Test;

    #[Test]
    final class OrderTest
    {
        public function placesOrder(): void
        {
            $order = Order::place(customerId: 42, items: ['sku-1' => 2, 'sku-2' => 1]);

            Assert::string($order->number)
                ->contains('ORD-')
                ->notContains(' ');

            Assert::int($order->total)
                ->greaterThan(0)
                ->lessThanOrEqual(1_000_000);

            Assert::array($order->lines)
                ->hasCount(2)
                ->hasKeys('sku-1', 'sku-2')
                ->doesNotHaveKeys('sku-3')
                ->allOf(Order\Line::class);

            Assert::same($order->customerId, 42);
            Assert::null($order->paidAt);
        }
    }
}
