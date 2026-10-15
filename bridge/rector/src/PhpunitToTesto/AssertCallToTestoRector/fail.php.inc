<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    public function test()
    {
        $this->fail('nope');
    }
}
-----
<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    public function test()
    {
        \Testo\Assert::fail('nope');
    }
}
