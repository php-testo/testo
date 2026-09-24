<?php

class SomeTest
{
    public function test()
    {
        $mock->shouldReceive('a', 'b')->andReturn(1);
        $mock->shouldReceive('a', 'b')->once()->with(\Mockery::type('int'), $value)->andReturn([1, 2]);
        $mock->shouldReceive(['a' => 1, 'b' => 2]);
        $mock->shouldReceive(['a' => 1], 'c')->once();
        $mock->allows(['a' => 1, 'b' => $value]);
        $this->mock->shouldNotReceive('a', 'b');
        $mock->shouldNotReceive()->run('x');
        $mock->shouldReceive()->run('x')->andReturn(1);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $mock->allows('a')->returns(1);
        $mock->allows('b')->returns(1);
        $mock->expects('a')->with(\JMac\Testing\Matching\Argument::type('int'), $value)->returns([1, 2]);
        $mock->expects('b')->with(\JMac\Testing\Matching\Argument::type('int'), $value)->returns([1, 2]);
        $mock->allows('a')->returns(1);
        $mock->allows('b')->returns(2);
        $mock->expects('a')->returns(1);
        $mock->expects('c');
        $mock->allows('a')->returns(1);
        $mock->allows('b')->returns($value);
        $this->mock->expects('a')->never();
        $this->mock->expects('b')->never();
        $mock->expects('run')->with('x')->never();
        $mock->allows('run')->with('x')->returns(1);
    }
}
