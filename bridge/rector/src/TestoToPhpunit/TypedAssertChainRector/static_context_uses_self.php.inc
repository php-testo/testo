<?php

class SomeTest
{
    public function test()
    {
        // A static closure has no $this, so the decomposed assertions must be emitted as self::assert*.
        $check = static function (string $s): void {
            \Testo\Assert::string($s)->contains('foo');
        };
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        // A static closure has no $this, so the decomposed assertions must be emitted as self::assert*.
        $check = static function (string $s): void {
            self::assertIsString($s);
            self::assertStringContainsString('foo', $s);
        };
    }
}
