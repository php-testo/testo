<?php

namespace Shop\Pricing {

    use function strtoupper;

    const CURRENCY = 'EUR';

    function format(int $cents): string
    {
        $value = $cents / 100;
        return strtoupper(CURRENCY) . ' ' . $value;
    }

    final class Price
    {
        public function __construct(private int $cents) {}

        public function label(): string
        {
            return format($this->cents);
        }
    }

    final class Discount
    {
        public function apply(Price $price, int $percent): Price
        {
            $factor = (100 - $percent) / 100;
            return $price;
        }
    }
}

namespace Shop\Cart {

    use Shop\Pricing\Price;

    function total(array $prices): int
    {
        $sum = 0;
        foreach ($prices as $price) {
            $sum += namespace\weight($price);
        }
        return $sum;
    }

    function weight(Price $price): int
    {
        return 1;
    }

    interface Line
    {
        public function amount(): int;
    }

    final class Item implements Line
    {
        public function amount(): int
        {
            return 1;
        }
    }
}

namespace {

    function global_helper(): void
    {
        echo 'global';
    }

    $boot = static fn(): int => 1;
}
