<?php

class WithRemainingArgumentsTest
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

class WithRemainingArgumentsTest
{
    public function test()
    {
        $validator->allows('getValue')->with('abc', \JMac\Testing\Matching\Argument::remaining())->returns(123);
        $validator->expects('check')->times(1)->returns(true);
        $validator->allows('validate')->with(...$expected, ...[\JMac\Testing\Matching\Argument::remaining()])->returns(false);
    }
}
