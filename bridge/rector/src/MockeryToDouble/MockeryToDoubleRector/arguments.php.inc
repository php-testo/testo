<?php

class SomeTest
{
    public function test(array $list, \Closure $check)
    {
        $mock->shouldReceive('a')->withArgs([1, 'x'])->andReturn(1);
        $mock->shouldReceive('b')->withArgs(fn ($x, $y) => $x > $y)->andReturn(1);
        $mock->shouldReceive('c')->withNoArgs()->andReturn(1);
        $mock->shouldReceive('d')->withAnyArgs()->andReturn(1);
        $mock->shouldReceive('e')->withArgs([\Mockery::type('int'), 2])->andReturn(1);
        $mock->shouldReceive('f')->withArgs($list)->andReturn(1);
        $mock->shouldReceive('g')->withArgs($check)->andReturn(1);
        $mock->shouldReceive('h')->withSomeOfArgs(1, 'x')->andReturn(1);
    }
}
-----
<?php

class SomeTest
{
    public function test(array $list, \Closure $check)
    {
        $mock->allows('a')->with(1, 'x')->returns(1);
        $mock->allows('b')->with(\JMac\Testing\Matching\Argument::all(fn ($x, $y) => $x > $y))->returns(1);
        $mock->allows('c')->with(\JMac\Testing\Matching\Argument::none())->returns(1);
        $mock->allows('d')->returns(1);
        $mock->allows('e')->with(\JMac\Testing\Matching\Argument::type('int'), 2)->returns(1);
        $mock->allows('f')->with(...$list)->returns(1);
        $mock->allows('g')->with(\JMac\Testing\Matching\Argument::all($check))->returns(1);
        $mock->allows('h')->with(\JMac\Testing\Matching\Argument::all(fn(...$args) => in_array(1, $args, true) && in_array('x', $args, true)))->returns(1);
    }
}
