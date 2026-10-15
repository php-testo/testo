<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    public function test()
    {
        $this->assertSame(3, $result);
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
