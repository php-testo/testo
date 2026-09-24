<?php

class SomeTest
{
    public function test()
    {
        \Mockery::mock(Dependency::class)->shouldReceive('run')->andReturn(1)->passthru();
        $service = new Service(\Mockery::mock(Dependency::class)->shouldReceive('run')->andReturn(1)->getMock());
        $kept = \Mockery::mock(Dependency::class)->shouldReceive('run')->andReturnUndefined()->getMock();
    }
}
