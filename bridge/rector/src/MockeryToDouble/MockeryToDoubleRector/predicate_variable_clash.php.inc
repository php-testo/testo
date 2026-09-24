<?php

class SomeTest
{
    public function test()
    {
        $value = 'k';
        $mock->shouldReceive('f')->with(\Mockery::hasKey($value))->andReturn(1);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $value = 'k';
        $mock->allows('f')->with(\JMac\Testing\Matching\Argument::satisfies(fn($value2) => (is_array($value2) || $value2 instanceof \ArrayAccess) && array_key_exists($value, (array) $value2)))->returns(1);
    }
}
