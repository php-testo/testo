<?php

class SomeTest
{
    public function test()
    {
        $dep->expects($this->once())->method('run')->with($this->equalTo('a'), $this->anything(), $this->isInstanceOf(Foo::class))->willReturn('y');
        $dep->method('cb')->with($this->callback(fn ($x) => $x > 0), $this->identicalTo(3), $this->matchesRegularExpression('/x/'))->willReturn('z');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->expects('run')->times(1)->with('a', \JMac\Testing\Matching\Argument::any(), \JMac\Testing\Matching\Argument::type(Foo::class))->returns('y');
        $dep->allows('cb')->with(\JMac\Testing\Matching\Argument::satisfies(fn ($x) => $x > 0), \JMac\Testing\Matching\Argument::same(3), \JMac\Testing\Matching\Argument::matches('/x/'))->returns('z');
    }
}
