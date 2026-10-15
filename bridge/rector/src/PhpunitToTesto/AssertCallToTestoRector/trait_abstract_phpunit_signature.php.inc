<?php

trait RequiresPhpunitAssertions
{
    abstract public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void;

    abstract public static function fail(string $message = ''): never;

    public function check($value): void
    {
        $this->assertSame(1, $value);
        $value === null and $this->fail('null');
    }
}
-----
<?php

trait RequiresPhpunitAssertions
{
    abstract public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void;

    abstract public static function fail(string $message = ''): never;

    public function check($value): void
    {
        \Testo\Assert::same($value, 1);
        $value === null and \Testo\Assert::fail('null');
    }
}
