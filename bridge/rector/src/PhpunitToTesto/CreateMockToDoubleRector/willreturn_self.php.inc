<?php

class SomeTest
{
    public function test()
    {
        $dep->expects($this->once())->method('chain')->willReturnSelf();
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->expects('chain')->times(1)->returns($dep);
    }
}
