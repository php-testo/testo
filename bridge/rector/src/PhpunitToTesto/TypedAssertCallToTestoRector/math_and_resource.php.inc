<?php

class MathAndResourceTest
{
    public function test(float $ratio, int $count, int|float $number, string $numeric, mixed $unknown)
    {
        $this->assertFinite($ratio);
        $this->assertInfinite($count, 'infinite');
        $this->assertNan($number);

        // PHPUnit fails a non-number, is_finite() coerces a numeric string.
        $this->assertFinite($numeric);
        $this->assertNan($unknown);

        $this->assertIsResource($handle);
        $this->assertIsNotResource($value, 'not a resource');
        $this->assertIsClosedResource($handle);
        $this->assertIsNotClosedResource($handle, 'still open');
    }
}
-----
<?php

class MathAndResourceTest
{
    public function test(float $ratio, int $count, int|float $number, string $numeric, mixed $unknown)
    {
        \Testo\Assert::true(\is_finite($ratio));
        \Testo\Assert::true(\is_infinite($count), 'infinite');
        \Testo\Assert::true(\is_nan($number));

        // PHPUnit fails a non-number, is_finite() coerces a numeric string.
        $this->assertFinite($numeric);
        $this->assertNan($unknown);

        \Testo\Assert::true(\str_starts_with(\gettype($handle), 'resource'));
        \Testo\Assert::false(\str_starts_with(\gettype($value), 'resource'), 'not a resource');
        \Testo\Assert::same(\gettype($handle), 'resource (closed)');
        \Testo\Assert::notSame(\gettype($handle), 'resource (closed)', 'still open');
    }
}
