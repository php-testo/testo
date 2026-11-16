<?php

class NamedTypedHelpersTest extends \PHPUnit\Framework\TestCase
{
    public function test(array $rows, array $other, string $json, $x)
    {
        $this->assertEmpty(message: 'no rows', actual: $rows);
        $this->assertJson(actual: $json);
        $this->assertSameSize(actual: $rows, expected: $other);
        $this->assertNotInstanceOf(actual: $x, expected: \Countable::class);
    }
}
-----
<?php

class NamedTypedHelpersTest extends \PHPUnit\Framework\TestCase
{
    public function test(array $rows, array $other, string $json, $x)
    {
        \Testo\Assert::blank($rows, 'no rows');
        \Testo\Assert::json($json);
        \Testo\Assert::iterable($rows)->sameSizeAs($other);
        \Testo\Assert::false($x instanceof \Countable);
    }
}
