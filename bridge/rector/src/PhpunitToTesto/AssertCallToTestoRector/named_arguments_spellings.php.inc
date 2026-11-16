<?php

class NamedFlatAssertionSpellingsTest extends \PHPUnit\Framework\TestCase
{
    public function test($x, $items)
    {
        static::assertSame(actual: $x, expected: 1);
        parent::assertNotSame(actual: $x, expected: 2);
        \PHPUnit\Framework\Assert::assertEquals(actual: $x, expected: 1);
        \PHPUnit\Framework\TestCase::assertNotEquals(actual: $x, expected: 2);
        $this->assertInstanceOf(actual: $x, expected: \Countable::class);
        $this->assertContains(haystack: $items, needle: 1);
        $this->fail(message: 'unreachable');
    }
}
-----
<?php

class NamedFlatAssertionSpellingsTest extends \PHPUnit\Framework\TestCase
{
    public function test($x, $items)
    {
        \Testo\Assert::same($x, 1);
        \Testo\Assert::notSame($x, 2);
        \Testo\Assert::equals($x, 1);
        \Testo\Assert::notEquals($x, 2);
        \Testo\Assert::instanceOf($x, \Countable::class);
        \Testo\Assert::contains($items, 1);
        \Testo\Assert::fail('unreachable');
    }
}
