<?php

class SomeTest
{
    public function test()
    {
        $mock->shouldReceive('run')->andReturn(1)->byDefault();
        $mock->shouldReceive('run')->byDefault()->andReturnNull();
        $mock->shouldReceive('run')->andReturn(1)->getMock();
        \Mockery::mock(Dependency::class)->shouldReceive('run')->andReturn(1)->byDefault();
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $mock->allows('run')->returns(1);
        $mock->allows('run')->returns(null);
        $mock->allows('run')->returns(1);
        \JMac\Testing\Double::for(Dependency::class)->strict()->allows('run')->returns(1);
    }
}
