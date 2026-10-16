<?php

declare(strict_types=1);

final class StrictStringRuntimeContracts extends \PHPUnit\Framework\TestCase
{
    public function testValidNamed(): void
    {
        $this->assertStringContainsString(haystack: 'xyz', needle: 'x');
    }

    public function testIntegerMustError(): void
    {
        $this->assertStringContainsString('2', 123);
    }

    public function testStringableMustError(): void
    {
        $this->assertStringContainsString('x', new class implements \Stringable {
            public function __toString(): string { return 'xyz'; }
        });
    }

    public function testInvalidMessageMustError(): void
    {
        $this->assertStringContainsString('x', 'xyz', message: 123);
    }
}
