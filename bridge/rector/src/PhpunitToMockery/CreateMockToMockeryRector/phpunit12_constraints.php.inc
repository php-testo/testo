<?php

class SomeTest
{
    public function test()
    {
        $dep->method('a')->with($this->isInt(), $this->isString(), $this->isBool(), $this->isFloat(), $this->isArray(), $this->isObject())->willReturn('r');
        $dep->method('b')->with($this->isCallable(), $this->isIterable(), $this->isNumeric(), $this->isScalar(), $this->isResource(), $this->isClosedResource())->willReturn('r');
        $dep->method('c')->with($this->containsOnlyString(), $this->containsOnlyNumeric())->willReturn('r');
        $dep->method('d')->with($this->logicalNot($this->isInt()), $this->stringContains(''))->willReturn('r');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->shouldReceive('a')->with(\Mockery::type('int'), \Mockery::type('string'), \Mockery::type('bool'), \Mockery::type('float'), \Mockery::type('array'), \Mockery::type('object'), \Mockery::andAnyOtherArgs())->andReturn('r');
        $dep->shouldReceive('b')->with(\Mockery::type('callable'), \Mockery::type('iterable'), \Mockery::type('numeric'), \Mockery::type('scalar'), \Mockery::on(fn($value) => is_resource($value) || gettype($value) === 'resource (closed)'), \Mockery::on(fn($value) => gettype($value) === 'resource (closed)'), \Mockery::andAnyOtherArgs())->andReturn('r');
        $dep->shouldReceive('c')->with(\Mockery::on(fn($value) => is_iterable($value) && array_filter([...$value], fn($valueItem) => !is_string($valueItem)) === []), \Mockery::on(fn($value) => is_iterable($value) && array_filter([...$value], fn($valueItem) => !is_numeric($valueItem)) === []), \Mockery::andAnyOtherArgs())->andReturn('r');
        $dep->shouldReceive('d')->with(\Mockery::on(fn($value) => !is_int($value)), \Mockery::on(fn($value) => true), \Mockery::andAnyOtherArgs())->andReturn('r');
    }
}
