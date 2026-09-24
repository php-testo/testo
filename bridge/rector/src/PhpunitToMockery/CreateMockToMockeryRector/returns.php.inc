<?php

class SomeTest
{
    public function test()
    {
        $dep->method('run')->willReturn('y');
        $dep->method('boom')->willThrowException(new \RuntimeException());
        $dep->method('lazy')->willReturnCallback(fn () => 1);
        $dep->method('seq')->willReturnOnConsecutiveCalls(1, 2, 3);
        $dep->method('args')->willReturnArgument(1);
        $dep->expects($this->once())->method('chain')->willReturnSelf();
        $dep->expects($this->once())->method('a')->will($this->returnValue('y'));
        $dep->method('b')->will($this->throwException(new \RuntimeException()));
        $dep->method('c')->will($this->onConsecutiveCalls(1, 2));
        $dep->method('d')->will($this->returnArgument(0));
        $dep->method('e')->will($this->returnCallback(fn () => 2));
        $dep->method('f')->will($this->returnSelf());
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->shouldReceive('run')->andReturn('y');
        $dep->shouldReceive('boom')->andThrow(new \RuntimeException());
        $dep->shouldReceive('lazy')->andReturnUsing(fn () => 1);
        $dep->shouldReceive('seq')->andReturn(1, 2, 3);
        $dep->shouldReceive('args')->andReturnArg(1);
        $dep->shouldReceive('chain')->once()->andReturnSelf();
        $dep->shouldReceive('a')->once()->andReturn('y');
        $dep->shouldReceive('b')->andThrow(new \RuntimeException());
        $dep->shouldReceive('c')->andReturn(1, 2);
        $dep->shouldReceive('d')->andReturnArg(0);
        $dep->shouldReceive('e')->andReturnUsing(fn () => 2);
        $dep->shouldReceive('f')->andReturnSelf();
    }
}
