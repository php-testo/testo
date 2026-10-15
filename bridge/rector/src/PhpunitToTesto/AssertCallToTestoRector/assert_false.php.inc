<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    public function test()
    {
        $this->assertFalse($flag);
    }
}
-----
<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    public function test()
    {
        \Testo\Assert::false($flag);
    }
}
