<?php

class SomeTest
{
    public function test()
    {
        $dep->method('run')->willReturnMap([['a', 1], ['b', 2]]);
        $dep->method('and')->with($this->logicalAnd($this->greaterThan(5), $this->lessThan(9)))->willReturn('u');
        $dep->method('delta')->with($this->equalToWithDelta(5.0, 0.1))->willReturn('d');
        $dep->method('ci')->with($this->stringContains('x', true))->willReturn('c');
    }
}
