<?php

class UnsupportedNamedFlatAssertions extends \PHPUnit\Framework\TestCase
{
    public function run(): void
    {
        $this->assertSame(expected: 1, actual: 1);
        $this->assertTrue(actual: true);
        $this->assertSame(...$arguments);
    }
}
