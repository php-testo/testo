<?php

class SomeTest
{
    public function test()
    {
        $dep->method('a')->will($this->onConsecutiveCalls(1, 2));
        $dep->method('b')->will($this->returnArgument(0));
        $dep->expects($this->once())->method('c')->will($this->returnSelf());
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->allows('a')->returns(1, 2);
        $dep->allows('b')->resolves(fn(...$args) => $args[0]);
        $dep->expects('c')->times(1)->returns($dep);
    }
}
