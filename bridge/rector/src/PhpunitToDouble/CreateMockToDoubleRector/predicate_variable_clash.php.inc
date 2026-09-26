<?php

class SomeTest
{
    public function test()
    {
        $value = 5;
        $dep->method('n')->with($this->greaterThan($value))->willReturn('u');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $value = 5;
        $dep->allows('n')->with(\JMac\Testing\Matching\Argument::satisfies(fn($value2) => $value2 > $value), \JMac\Testing\Matching\Argument::remaining())->returns('u');
    }
}
