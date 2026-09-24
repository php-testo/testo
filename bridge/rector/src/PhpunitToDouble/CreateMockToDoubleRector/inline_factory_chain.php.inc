<?php

class SomeTest
{
    public function test()
    {
        $this->createMock(Dependency::class)->method('run')->willReturn('y');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \JMac\Testing\Double::for(Dependency::class)->allows('run')->returns('y');
    }
}
