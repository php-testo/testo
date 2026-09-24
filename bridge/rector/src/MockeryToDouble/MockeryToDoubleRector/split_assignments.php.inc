<?php

class SomeTest
{
    public function test()
    {
        $quick = \Mockery::mock(Dependency::class, ['run' => 1, 'stop' => $value]);
        $this->spy = \Mockery::spy(Dependency::class, ['run' => 1]);
        $inline = \Mockery::mock(Dependency::class)->shouldReceive('run')->once()->andReturn(1)->getMock();
        $loose = \Mockery::mock(Dependency::class)->shouldIgnoreMissing()->shouldReceive('run')->andReturn(1)->getMock();
        $partial = \Mockery::mock(Real::class)->makePartial()->shouldReceive('a', 'b')->andReturn(2)->getMock();
        $magic = \Mockery::mock(Dependency::class)->allows()->run('x')->andReturn(1)->getMock();
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $quick = \JMac\Testing\Double::for(Dependency::class)->strict();
        $quick->allows('run')->returns(1);
        $quick->allows('stop')->returns($value);
        $this->spy = \JMac\Testing\Double::for(Dependency::class);
        $this->spy->allows('run')->returns(1);
        $inline = \JMac\Testing\Double::for(Dependency::class)->strict();
        $inline->expects('run')->returns(1);
        $loose = \JMac\Testing\Double::for(Dependency::class);
        $loose->allows('run')->returns(1);
        $partial = \JMac\Testing\Double::for(Real::class)->passthru();
        $partial->allows('a')->returns(2);
        $partial->allows('b')->returns(2);
        $magic = \JMac\Testing\Double::for(Dependency::class)->strict();
        $magic->allows('run')->with('x')->returns(1);
    }
}
