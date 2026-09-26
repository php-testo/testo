<?php

final class ContainsOnlyFinalItem {}

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
        $this->assertContainsOnlyInstancesOf(\Closure::class, $callbacks);
        $this->assertContainsOnlyInstancesOf('\Closure', $callbacks, 'closures');
        $this->assertContainsOnlyInstancesOf(ContainsOnlyFinalItem::class, $items);

        // allOf() matches the exact class and reports an object by its class name.
        $this->assertContainsOnlyInstancesOf(\DateTime::class, $dates);
        $this->assertContainsOnlyInstancesOf(\Countable::class, $bags);
        $this->assertContainsOnlyInstancesOf($class, $things);
        $this->assertContainsOnlyInstancesOf(self::class, $tests);
        $this->assertContainsOnlyObject($objects);
        $this->assertContainsOnlyScalar($scalars);
        $this->assertContainsNotOnlyInt($mixed);
    }
}
-----
<?php

final class ContainsOnlyFinalItem {}

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
        \Testo\Assert::iterable($callbacks)->allOf(\Closure::class);
        \Testo\Assert::iterable($callbacks)->allOf(\Closure::class, 'closures');
        \Testo\Assert::iterable($items)->allOf(ContainsOnlyFinalItem::class);

        // allOf() matches the exact class and reports an object by its class name.
        $this->assertContainsOnlyInstancesOf(\DateTime::class, $dates);
        $this->assertContainsOnlyInstancesOf(\Countable::class, $bags);
        $this->assertContainsOnlyInstancesOf($class, $things);
        $this->assertContainsOnlyInstancesOf(self::class, $tests);
        $this->assertContainsOnlyObject($objects);
        $this->assertContainsOnlyScalar($scalars);
        $this->assertContainsNotOnlyInt($mixed);
    }
}
