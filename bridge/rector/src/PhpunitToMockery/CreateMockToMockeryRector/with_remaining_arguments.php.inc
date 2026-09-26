<?php

class WithRemainingArgumentsMockeryTest
{
    public function test()
    {
        $validator->method('getValue')->with('abc')->willReturn(123);
        $validator->expects($this->once())->method('check')->with()->willReturn(true);
        $validator->method('validate')->with(...$expected)->willReturn(false);
    }
}
-----
<?php

class WithRemainingArgumentsMockeryTest
{
    public function test()
    {
        $validator->shouldReceive('getValue')->with('abc', \Mockery::andAnyOtherArgs())->andReturn(123);
        $validator->shouldReceive('check')->once()->andReturn(true);
        $validator->shouldReceive('validate')->with(...$expected, ...[\Mockery::andAnyOtherArgs()])->andReturn(false);
    }
}
