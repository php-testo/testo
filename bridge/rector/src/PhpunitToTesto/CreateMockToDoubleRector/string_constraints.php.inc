<?php

class SomeTest
{
    public function test()
    {
        $dep->method('s')->with($this->stringContains('x'), $this->stringStartsWith('a'), $this->stringEndsWith('z'), $this->arrayHasKey('k'))->willReturn('t');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->allows('s')->with(\JMac\Testing\Matching\Argument::satisfies(fn($value) => str_contains($value, 'x')), \JMac\Testing\Matching\Argument::satisfies(fn($value) => str_starts_with($value, 'a')), \JMac\Testing\Matching\Argument::satisfies(fn($value) => str_ends_with($value, 'z')), \JMac\Testing\Matching\Argument::satisfies(fn($value) => array_key_exists('k', $value)))->returns('t');
    }
}
