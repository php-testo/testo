<?php

class SomeTest
{
    public function test()
    {
        $dep->method('run')->willReturnMap([['a', 1], ['b', 2]]);
        $dep->method('c')->with($this->greaterThan(5))->willReturn('u');
    }
}
