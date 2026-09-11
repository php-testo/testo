<?php

class SomeTest
{
    public function test()
    {
        $dep = $this->createStub(Dependency::class);
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
