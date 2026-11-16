<?php

class UnsupportedNamedFlatAssertionsTest extends \PHPUnit\Framework\TestCase
{
    public function test($x, $arguments)
    {
        $this->assertTrue(actual: true);
        $this->assertSame(Expected: 1, actual: $x);
        $this->assertSame(1, $x, wrong: 'context');
        $this->assertEquals(expected: 1, message: 'no actual');
        $this->assertSame(...$arguments);
        $this->assertSame(...);
    }
}
