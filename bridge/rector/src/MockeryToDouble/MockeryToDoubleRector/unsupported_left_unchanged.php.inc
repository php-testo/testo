<?php

class SomeTest
{
    public function test($unknown)
    {
        $mock->shouldReceive('run')->with('x')->andReturn(1)->byDefault();
        $mock->shouldReceive('run')->once()->byDefault();
        $mock->shouldReceive('run')->passthru();
        $mock->shouldReceive('run')->andSet('prop', 1);
        $mock->shouldReceive('run')->ordered('group');
        $mock->shouldReceive('run')->globally()->ordered();
        $mock->shouldReceive('a->b')->andReturn(1);
        $mock->shouldReceive($map);
        $mock->shouldReceive('a', 'b')->andReturn(new Value());
        $mock->shouldReceive('a', 'b')->andReturnUsing(makeCallback());
        $mocks[0]->shouldReceive('a', 'b')->andReturn(1);
        $mock->allows(['a' => 1])->andReturn(2);
        $mock->shouldReceive('run')->andReturnUndefined();
        $mock->shouldReceive('run')->andReturnUsing($a, $b);
        $mock->shouldReceive('run')->andThrow($exception, $message);
        $mock->shouldReceive('run')->andReturnValues($unknown);
        $mock->shouldReceive('run')->withArgs($unknown);
        $mock->shouldReceive('run')->withSomeOfArgs(\Mockery::any());
        $mock->shouldReceive('run')->withSomeOfArgs($args);
        $mock->shouldReceive('run')->with(\Mockery::subset(['a' => 1], $strict))->andReturn(1);
        $mock->shouldReceive('run')->with(\Mockery::not(\Mockery::type('int')))->andReturn(1);
        $mock->shouldReceive('run')->with(equalTo(5))->andReturn(1);
        $mock->shouldReceive('run')->atLeast()->andReturn(1);
        $mock->shouldReceive('run')->once()->twice();
        $spy->shouldHaveReceived('run')->andReturn(1);
        $spy->shouldHaveReceived('run', $unknown);
        $spy->shouldHaveBeenCalled([1]);
    }
}
