<?php

class SomeTest
{
    public function test()
    {
        $dep->expects($this->once())->method('run')->with('x')->willReturn('y');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->expects('run')->times(1)->with('x')->returns('y');
    }
}
