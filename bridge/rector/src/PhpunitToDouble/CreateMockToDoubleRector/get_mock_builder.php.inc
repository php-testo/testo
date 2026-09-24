<?php

class SomeTest
{
    public function test()
    {
        $dep = $this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->getMock();
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep = \JMac\Testing\Double::for(Dependency::class);
    }
}
