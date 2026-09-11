<?php

class SomeTest
{
    public function test()
    {
        $dep->method('run')->willReturnMap([['a', 1], ['b', 2]]);
        $dep->expects($this->once())->method('self')->willReturnSelf();
    }
}
