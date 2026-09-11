<?php

class SomeTest
{
    public function test()
    {
        $dep->expects($this->once())->method('run')->will($this->returnValue('y'));
        $dep->method('boom')->will($this->throwException(new \RuntimeException()));
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->expects('run')->times(1)->returns('y');
        $dep->allows('boom')->throws(new \RuntimeException());
    }
}
