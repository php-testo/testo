<?php

class SomeTest
{
    public function test()
    {
        $mock->allows('run')->andReturn(1);
        $mock->expects('run')->andReturn(1);
        $mock->expects('run')->twice()->andReturn(1);
        $mock->allows()->run('x')->andReturn(1);
        $mock->expects()->run('x', 2)->andReturn(1);
        $mock->allows()->run()->andReturn(1);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $mock->allows('run')->returns(1);
        $mock->expects('run')->returns(1);
        $mock->expects('run')->times(2)->returns(1);
        $mock->allows('run')->with('x')->returns(1);
        $mock->expects('run')->with('x', 2)->returns(1);
        $mock->allows('run')->with(\JMac\Testing\Matching\Argument::none())->returns(1);
    }
}
