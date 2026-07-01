<?php

class SomeTest
{
    public function test()
    {
        $this->assertNotEquals($expected, $value);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::notEquals($value, $expected);
    }
}
