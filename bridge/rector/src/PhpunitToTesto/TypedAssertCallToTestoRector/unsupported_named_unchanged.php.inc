<?php

class UnsupportedNamedAssertions extends \PHPUnit\Framework\TestCase
{
    public function run(): void
    {
        $this->assertStringContainsString(wrong: 'x', haystack: 'xyz');
        $this->assertStringContainsString(needle: 'x', haystack: 'xyz', wrong: 'context');
        $this->assertStringContainsString(...$arguments);
        $this->assertStringContainsString(...);
        $this->assertIsString(actual: 'xyz');
        $this->assertGreaterThan(expected: 0, actual: 1);
    }
}
