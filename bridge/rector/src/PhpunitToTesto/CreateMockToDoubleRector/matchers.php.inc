<?php

class SomeTest
{
    public function test()
    {
        $dep->expects($this->never())->method('a');
        $dep->expects($this->exactly(3))->method('b')->willReturn('b');
        $dep->expects($this->any())->method('c')->willReturn('c');
        $dep->expects($this->atLeastOnce())->method('d')->willReturn('d');
        $dep->expects($this->atLeast(2))->method('e')->willReturn('e');
        $dep->expects($this->atMost(5))->method('f')->willReturn('f');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->expects('a')->never();
        $dep->expects('b')->times(3)->returns('b');
        $dep->allows('c')->returns('c');
        $dep->expects('d')->times(minimum: 1)->returns('d');
        $dep->expects('e')->times(minimum: 2)->returns('e');
        $dep->expects('f')->times(maximum: 5)->returns('f');
    }
}
