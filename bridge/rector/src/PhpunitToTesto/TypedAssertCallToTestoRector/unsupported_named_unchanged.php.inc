<?php

class UnsupportedNamedTypedAssertionsTest extends \PHPUnit\Framework\TestCase
{
    public function test($arguments)
    {
        $this->assertStringContainsString(wrong: 'x', haystack: 'xyz');
        $this->assertStringContainsString(needle: 'x', haystack: 'xyz', wrong: 'context');
        $this->assertStringContainsString(haystack: 'xyz', message: 'no needle');
        $this->assertStringContainsString(...$arguments);
        $this->assertStringContainsString(...);
        $this->assertGreaterThan(expected: 0, actual: 1);
    }
}
