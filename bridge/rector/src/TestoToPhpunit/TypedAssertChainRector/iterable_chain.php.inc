<?php

class IterableChainTest
{
    public function test(\ArrayObject $bag, array $expected)
    {
        \Testo\Assert::iterable($items)->contains('x')->notContains('y')->allInstanceOf(\Countable::class);
        \Testo\Assert::iterable($ids)->allOf('int');
        \Testo\Assert::iterable($flags)->allOf('boolean')->allOf('Bool');
        \Testo\Assert::iterable($bag)->hasCount(2)->sameSizeAs($expected);
        \Testo\Assert::array($rows)->allOf('array')->allInstanceOf(\stdClass::class)->sameSizeAs($expected);
    }
}
-----
<?php

class IterableChainTest
{
    public function test(\ArrayObject $bag, array $expected)
    {
        $this->assertIsIterable($items);
        $this->assertContains('x', $items);
        $this->assertNotContains('y', $items);
        $this->assertContainsOnlyInstancesOf(\Countable::class, $items);
        $this->assertIsIterable($ids);
        $this->assertContainsOnlyInt($ids);
        $this->assertIsIterable($flags);
        $this->assertContainsOnlyBool($flags);
        $this->assertContainsOnlyBool($flags);
        $this->assertIsIterable($bag);
        $this->assertCount(2, $bag);
        $this->assertSameSize($expected, $bag);
        $this->assertIsArray($rows);
        $this->assertContainsOnlyArray($rows);
        $this->assertContainsOnlyInstancesOf(\stdClass::class, $rows);
        $this->assertSameSize($expected, $rows);
    }
}
