<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    public function test()
    {
        $this->assertTrue($flag);
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
