<?php

class SomeTest
{
    public function test()
    {
        $dep->expects($this->once())->method('run')->withAnyParameters()->willReturn('y');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->expects('run')->times(1)->returns('y');
    }
}
