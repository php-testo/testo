<?php

trait ResponseAssertions
{
    abstract public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void;

    abstract public static function fail(string $message = ''): never;

    abstract public function assertIsString($actual): mixed;

    abstract protected function assertValidResponse($response): void;

    protected function check($value): void
    {
        $this->assertSame(1, $value);
    }
}
-----
<?php

trait ResponseAssertions
{
    abstract public function assertIsString($actual): mixed;

    abstract protected function assertValidResponse($response): void;

    protected function check($value): void
    {
        $this->assertSame(1, $value);
    }
}
