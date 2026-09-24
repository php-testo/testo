<?php

class SomeTest
{
    public function test()
    {
        $mock->shouldReceive('run')->andReturn('y');
        $mock->shouldReceive('run')->once()->with('x')->andReturn('y');
        $mock->shouldReceive('run')->twice();
        $mock->shouldReceive('run')->times(3)->andReturn(1, 2, 3);
        $mock->shouldReceive('run')->never();
        $mock->shouldReceive('run')->atLeast()->once();
        $mock->shouldReceive('run')->atLeast()->times($n);
        $mock->shouldReceive('run')->atMost()->twice();
        $mock->shouldReceive('run')->between(1, 3);
        $mock->shouldReceive('run')->zeroOrMoreTimes()->andReturnTrue();
        $mock->shouldReceive('run')->once()->ordered();
        $this->mock->shouldReceive('run')->once();
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $mock->allows('run')->returns('y');
        $mock->expects('run')->with('x')->returns('y');
        $mock->expects('run')->times(2);
        $mock->expects('run')->times(3)->returns(1, 2, 3);
        $mock->expects('run')->never();
        $mock->expects('run')->times(minimum: 1);
        $mock->expects('run')->times(minimum: $n);
        $mock->expects('run')->times(maximum: 2);
        $mock->expects('run')->times(1, 3);
        $mock->allows('run')->returns(true);
        $mock->expects('run')->ordered();
        $this->mock->expects('run');
    }
}
