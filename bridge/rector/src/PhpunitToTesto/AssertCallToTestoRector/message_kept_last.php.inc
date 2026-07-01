<?php

class SomeTest
{
    public function test()
    {
        $this->assertSame($expected, $actual, 'values must match');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::same($actual, $expected, 'values must match');
    }
}
