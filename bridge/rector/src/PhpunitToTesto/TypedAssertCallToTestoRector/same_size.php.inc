<?php

class SameSizeTest
{
    public function test(array $expected, array $actual, \ArrayObject $bag, \Traversable $stream, iterable $any, \Countable $counter)
    {
        $this->assertSameSize($expected, $actual);
        $this->assertSameSize($expected, $bag, 'same size');

        // PHPUnit counts a Countable that is not iterable and rejects a Traversable yielding a Generator.
        $this->assertSameSize($expected, $stream);
        $this->assertSameSize($any, $actual);
        $this->assertSameSize($counter, $actual);
    }
}
-----
<?php

class SameSizeTest
{
    public function test(array $expected, array $actual, \ArrayObject $bag, \Traversable $stream, iterable $any, \Countable $counter)
    {
        \Testo\Assert::iterable($actual)->sameSizeAs($expected);
        \Testo\Assert::iterable($bag)->sameSizeAs($expected, 'same size');

        // PHPUnit counts a Countable that is not iterable and rejects a Traversable yielding a Generator.
        $this->assertSameSize($expected, $stream);
        $this->assertSameSize($any, $actual);
        $this->assertSameSize($counter, $actual);
    }
}
