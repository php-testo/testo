<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    public function test()
    {
        self::assertSame(3, $result);
    }
}
-----
<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    public function test()
    {
        \Testo\Assert::same($result, 3);
    }
}
