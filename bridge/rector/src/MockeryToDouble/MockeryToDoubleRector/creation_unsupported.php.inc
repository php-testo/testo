<?php

class SomeTest
{
    public function test($unknown, \App\Real $real)
    {
        $bare = \Mockery::mock();
        $ctor = \Mockery::mock(Dependency::class, [1, 2]);
        $alias = \Mockery::mock('alias:App\Facade');
        $overload = \Mockery::mock('overload:App\Service');
        $partialMethods = \Mockery::mock('App\Service[run]');
        $untyped = \Mockery::mock($unknown);
        $closure = \Mockery::mock(fn () => 1);
        $ignoreWithValue = \Mockery::mock(Dependency::class)->shouldIgnoreMissing(5);
        $computedCtor = \Mockery::mock(Dependency::class, $args)->makePartial();
        $spyProxy = \Mockery::spy($real);
        $proxyIgnore = \Mockery::mock($real)->shouldIgnoreMissing();
        $service = new Service(\Mockery::mock(Dependency::class)->shouldReceive('run')->andReturn(1)->getMock());
    }
}
