<?php

class SomeTest
{
    public function test()
    {
        $dep->method('run')->willReturn('y');
        $dep->method('boom')->willThrowException(new \RuntimeException());
        $dep->method('lazy')->willReturnCallback(fn () => 1);
        $dep->method('seq')->willReturnOnConsecutiveCalls(1, 2, 3);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->allows('run')->returns('y');
        $dep->allows('boom')->throws(new \RuntimeException());
        $dep->allows('lazy')->resolves(fn () => 1);
        $dep->allows('seq')->returns(1, 2, 3);
    }
}
