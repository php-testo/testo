<?php

class SomeTest
{
    public function test()
    {
        $dep->method('a')->with($this->equalTo('a'), $this->anything(), $this->isInstanceOf(Foo::class))->willReturn('y');
        $dep->method('b')->with($this->callback(fn ($x) => $x > 0), $this->identicalTo(3), $this->matchesRegularExpression('/x/'))->willReturn('z');
        $dep->method('c')->with($this->isNull(), $this->isTrue(), $this->isFalse())->willReturn('w');
        $dep->method('d')->with($this->arrayHasKey('k'), $this->contains('v'), $this->containsEqual('e'))->willReturn('t');
        $dep->method('e')->with($this->isType('string'), self::isType('boolean'), $this->isType($type))->willReturn('u');
        $dep->method('f')->with(static::isType('integer'), $this->isType('double'), $this->isType('numeric'), $this->isType('scalar'), $this->isType('resource'))->willReturn('u');
        $dep->method('g')->with($this->isInstanceOf($class))->willReturn('u');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->shouldReceive('a')->with('a', \Mockery::any(), \Mockery::type(Foo::class), \Mockery::andAnyOtherArgs())->andReturn('y');
        $dep->shouldReceive('b')->with(\Mockery::on(fn ($x) => $x > 0), \Mockery::isSame(3), \Mockery::pattern('/x/'), \Mockery::andAnyOtherArgs())->andReturn('z');
        $dep->shouldReceive('c')->with(\Mockery::isSame(null), \Mockery::isSame(true), \Mockery::isSame(false), \Mockery::andAnyOtherArgs())->andReturn('w');
        $dep->shouldReceive('d')->with(\Mockery::hasKey('k'), \Mockery::hasValue('v'), \Mockery::hasValue('e'), \Mockery::andAnyOtherArgs())->andReturn('t');
        $dep->shouldReceive('e')->with(\Mockery::type('string'), \Mockery::type('bool'), \Mockery::type($type), \Mockery::andAnyOtherArgs())->andReturn('u');
        $dep->shouldReceive('f')->with(\Mockery::type('int'), \Mockery::type('float'), \Mockery::type('numeric'), \Mockery::type('scalar'), \Mockery::on(fn($value) => is_resource($value) || gettype($value) === 'resource (closed)'), \Mockery::andAnyOtherArgs())->andReturn('u');
        $dep->shouldReceive('g')->with(\Mockery::type($class), \Mockery::andAnyOtherArgs())->andReturn('u');
    }
}
