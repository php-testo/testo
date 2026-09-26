<?php

class ContainsOnlyTest
{
    public function test()
    {
        $this->assertContainsOnlyInt($ids);
        $this->assertContainsOnlyString($names, 'names only');
        $this->assertContainsOnlyArray($rows);
        $this->assertContainsOnlyBool($flags);
        $this->assertContainsOnlyFloat($ratios);
        $this->assertContainsOnlyNull($holes);
        $this->assertContainsOnlyInstancesOf(\DateTimeInterface::class, $dates);
        $this->assertContainsOnlyInstancesOf('\Closure', $callbacks, 'closures');
        $this->assertContainsOnlyInstancesOf($class, $things);
        self::assertContainsOnlyInstancesOf(self::class, $tests);

        // allOf() reports an object by its class name and knows no pseudo-type.
        $this->assertContainsOnlyObject($objects);
        $this->assertContainsOnlyScalar($scalars);
        $this->assertContainsNotOnlyInt($mixed);
    }
}
-----
<?php

class ContainsOnlyTest
{
    public function test()
    {
        \Testo\Assert::iterable($ids)->allOf('int');
        \Testo\Assert::iterable($names)->allOf('string', 'names only');
        \Testo\Assert::iterable($rows)->allOf('array');
        \Testo\Assert::iterable($flags)->allOf('bool');
        \Testo\Assert::iterable($ratios)->allOf('float');
        \Testo\Assert::iterable($holes)->allOf('null');
        \Testo\Assert::iterable($dates)->allInstanceOf(\DateTimeInterface::class);
        \Testo\Assert::iterable($callbacks)->allInstanceOf('\Closure', 'closures');
        \Testo\Assert::iterable($things)->allInstanceOf($class);
        \Testo\Assert::iterable($tests)->allInstanceOf(self::class);

        // allOf() reports an object by its class name and knows no pseudo-type.
        $this->assertContainsOnlyObject($objects);
        $this->assertContainsOnlyScalar($scalars);
        $this->assertContainsNotOnlyInt($mixed);
    }
}
