<?php

class SomeTest
{
    public function test()
    {
        $dep->method('a')->with($this->isInt(), $this->isString(), $this->isBool(), $this->isFloat(), $this->isArray(), $this->isObject())->willReturn('r');
        $dep->method('b')->with($this->isCallable(), $this->isIterable(), $this->isNumeric(), $this->isScalar(), $this->isResource(), $this->isClosedResource())->willReturn('r');
        $dep->method('c')->with($this->containsOnlyString(), $this->containsOnlyInt(), $this->containsOnlyClosedResource())->willReturn('r');
        $dep->method('d')->with($this->logicalNot($this->isInt()), $this->stringContains(''))->willReturn('r');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->allows('a')->with(\JMac\Testing\Matching\Argument::type('int'), \JMac\Testing\Matching\Argument::type('string'), \JMac\Testing\Matching\Argument::type('bool'), \JMac\Testing\Matching\Argument::type('float'), \JMac\Testing\Matching\Argument::type('array'), \JMac\Testing\Matching\Argument::type('object'), \JMac\Testing\Matching\Argument::remaining())->returns('r');
        $dep->allows('b')->with(\JMac\Testing\Matching\Argument::type('callable'), \JMac\Testing\Matching\Argument::type('iterable'), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_numeric($value)), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_scalar($value)), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_resource($value) || gettype($value) === 'resource (closed)'), \JMac\Testing\Matching\Argument::satisfies(fn($value) => gettype($value) === 'resource (closed)'), \JMac\Testing\Matching\Argument::remaining())->returns('r');
        $dep->allows('c')->with(\JMac\Testing\Matching\Argument::satisfies(fn($value) => is_iterable($value) && array_filter([...$value], fn($valueItem) => !is_string($valueItem)) === []), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_iterable($value) && array_filter([...$value], fn($valueItem) => !is_int($valueItem)) === []), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_iterable($value) && array_filter([...$value], fn($valueItem) => !(gettype($valueItem) === 'resource (closed)')) === []), \JMac\Testing\Matching\Argument::remaining())->returns('r');
        $dep->allows('d')->with(\JMac\Testing\Matching\Argument::not()->type('int'), \JMac\Testing\Matching\Argument::satisfies(fn($value) => true), \JMac\Testing\Matching\Argument::remaining())->returns('r');
    }
}
