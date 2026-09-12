<?php

class SomeTest
{
    public function test()
    {
        $dep->method('n')->with($this->greaterThan(5), $this->lessThan(9), $this->greaterThanOrEqual(1), $this->lessThanOrEqual(3))->willReturn('u');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->allows('n')->with(\JMac\Testing\Matching\Argument::satisfies(fn($value) => $value > 5), \JMac\Testing\Matching\Argument::satisfies(fn($value) => $value < 9), \JMac\Testing\Matching\Argument::satisfies(fn($value) => $value >= 1), \JMac\Testing\Matching\Argument::satisfies(fn($value) => $value <= 3))->returns('u');
    }
}
