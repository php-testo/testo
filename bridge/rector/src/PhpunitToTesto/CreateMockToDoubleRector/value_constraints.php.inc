<?php

class SomeTest
{
    public function test()
    {
        $dep->method('v')->with($this->isNull(), $this->isTrue(), $this->isFalse(), $this->isEmpty())->willReturn('w');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->allows('v')->with(null, true, false, \JMac\Testing\Matching\Argument::satisfies(fn($value) => empty($value)))->returns('w');
    }
}
