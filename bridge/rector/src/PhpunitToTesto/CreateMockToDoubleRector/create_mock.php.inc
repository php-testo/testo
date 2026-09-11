<?php

class SomeTest
{
    public function test()
    {
        $dep = $this->createMock(Dependency::class);
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
