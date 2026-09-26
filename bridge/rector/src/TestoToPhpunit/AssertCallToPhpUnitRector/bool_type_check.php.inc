<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::bool($flag);
    }

    public static function provider()
    {
        \Testo\Assert::bool($flag);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $this->assertIsBool($flag);
    }

    public static function provider()
    {
        self::assertIsBool($flag);
    }
}
