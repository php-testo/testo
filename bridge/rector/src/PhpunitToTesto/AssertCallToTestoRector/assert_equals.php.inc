<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    public function test()
    {
        $this->assertEquals($expected, $value);
    }
}
-----
<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    public function test()
    {
        \Testo\Assert::equals($value, $expected);
    }
}
