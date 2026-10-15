<?php

class CustomFlatAssertions
{
    public function assertSame($expected, $actual): string { return 'custom'; }
    public function run(): string { return $this->assertSame(1, 2); }
}

class CustomFlatStaticAssertions
{
    public static function assertTrue($actual): string { return 'custom'; }
    public function run(): string { return self::assertTrue(false); }
}

class MagicFlatAssertions
{
    public function __call(string $name, array $arguments): string { return 'magic'; }
    public function run(): string { return $this->assertSame(1, 2); }
}

class UnresolvedFlatAssertions extends \MissingTesting\TestCase
{
    public function run(): void { $this->assertSame(1, 2); }
}
