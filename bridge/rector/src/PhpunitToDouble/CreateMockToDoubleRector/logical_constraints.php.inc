<?php

class SomeTest
{
    public function test()
    {
        $dep->method('l')->with($this->logicalNot($this->equalTo('a')), $this->logicalNot($this->isType('int')), $this->logicalOr($this->equalTo('a'), $this->equalTo('b')))->willReturn('r');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->allows('l')->with(\JMac\Testing\Matching\Argument::not('a'), \JMac\Testing\Matching\Argument::not()->type('int'), \JMac\Testing\Matching\Argument::any('a', 'b'))->returns('r');
    }
}
