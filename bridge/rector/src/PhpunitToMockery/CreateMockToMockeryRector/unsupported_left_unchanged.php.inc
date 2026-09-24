<?php

class SomeTest
{
    public function test()
    {
        $query->where('active', true)->orderBy('name')->get();
        $dep->method('run');
        $dep->expects($matcher)->method('run')->willReturn(1);
        $dep->expects($this->once())->willReturn(1);
        $dep->method('ci')->with($this->stringContains('x', $ignoreCase))->willReturn('c');
        $dep->method('lines')->with($this->stringContains('x', false, true))->willReturn('c');
        $dep->method('type')->with($this->isType('unknown'))->willReturn('c');
        $dep->method('only')->with($this->containsOnly($type))->willReturn('c');
        $dep->method('obj')->with($this->objectEquals($expected, $method))->willReturn('c');
        $dep->method('format')->with($this->matches('%s'))->willReturn('c');
        $dep->method('named')->with($this->equalTo(value: 1))->willReturn('c');
        $dep->method('shadow')->willReturnMap($args);
        $dep->method('self')->willReturnSelf(1);
        $dep->method('run')->willReturnReference($ref);
        $dep->expects($this->once())->method('run')->withConsecutive([1], [2]);
        $mock->shouldReceive('run')->once()->andReturn(1);
    }
}
