<?php

class UserAssertions
{
    public function assertIsString($actual): string { return 'custom'; }
    public static function assertStringContainsString($needle, $haystack): string { return 'custom'; }

    public function run($receiver): string
    {
        $receiver->assertIsString(123);
        $this->assertIsString(123);
        self::assertStringContainsString('x', 123);
        return static::assertStringContainsString('x', 123);
    }
}

class InheritedUserAssertions extends UserAssertions
{
    public function run($receiver): string
    {
        return $this->assertIsString(123);
    }
}

class OverriddenUserAssertions extends UserAssertions
{
    public function assertIsString($actual): string { return 'overridden'; }

    public function run($receiver): string
    {
        return $this->assertIsString(123);
    }
}

class MagicAssertions
{
    public function __call(string $name, array $arguments): string { return 'magic'; }
    public function run(): string { return $this->assertIsString(123); }
}

class UnresolvedTypedAssertions extends \MissingTesting\TestCase
{
    public function run(): void { $this->assertIsString(123); }
}

trait RequiredUserAssertions
{
    abstract public function assertIsString($actual): mixed;
    public function run(): mixed { return $this->assertIsString(123); }
}
