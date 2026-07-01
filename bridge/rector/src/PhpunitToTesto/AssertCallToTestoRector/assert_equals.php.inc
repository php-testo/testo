<?php

class SomeTest
{
    public function test()
    {
        $this->assertEquals($expected, $value);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::equals($value, $expected);
    }
}
