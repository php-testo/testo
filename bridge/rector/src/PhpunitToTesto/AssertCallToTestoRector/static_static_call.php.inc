<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    public function test()
    {
        static::assertTrue($flag);
    }
}
-----
<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    public function test()
    {
        \Testo\Assert::true($flag);
    }
}
