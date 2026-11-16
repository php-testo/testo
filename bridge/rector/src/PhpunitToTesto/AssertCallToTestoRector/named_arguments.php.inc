<?php

class NamedFlatAssertionsTest extends \PHPUnit\Framework\TestCase
{
    public function test($x, $items, $flag)
    {
        $this->assertSame(expected: 1, actual: $x);
        $this->assertSame(actual: $x, expected: 1, message: 'same');
        $this->assertSame(1, message: 'mixed', actual: $x);
        $this->assertCount(haystack: $items, expectedCount: 2);
        $this->assertTrue(condition: $flag);
        $this->assertNotTrue(message: 'not true', condition: $flag);
        self::assertNull(actual: $x);
        \PHPUnit\Framework\assertEquals(actual: $x, expected: 1);
    }
}
-----
<?php

class NamedFlatAssertionsTest extends \PHPUnit\Framework\TestCase
{
    public function test($x, $items, $flag)
    {
        \Testo\Assert::same($x, 1);
        \Testo\Assert::same($x, 1, 'same');
        \Testo\Assert::same($x, 1, 'mixed');
        \Testo\Assert::count($items, 2);
        \Testo\Assert::true($flag);
        \Testo\Assert::notSame($flag, true, 'not true');
        \Testo\Assert::null($x);
        \Testo\Assert::equals($x, 1);
    }
}
