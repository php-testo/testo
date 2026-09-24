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
        $dep->allows('s')->with(\JMac\Testing\Matching\Argument::satisfies(fn($value) => is_string($value) && str_contains($value, 'x')), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_string($value) && str_starts_with($value, 'a')), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_string($value) && str_ends_with($value, 'z')), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_array($value) ? array_key_exists('k', $value) : $value instanceof \ArrayAccess && $value->offsetExists('k')))->returns('t');
    }
}
