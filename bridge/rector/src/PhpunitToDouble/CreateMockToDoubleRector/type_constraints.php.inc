<?php

class SomeTest
{
    public function test()
    {
        $dep->method('a')->with($this->isType('int'), $this->isType('integer'), $this->isType('boolean'), $this->isType('double'), $this->isType('real'))->willReturn('r');
        $dep->method('b')->with($this->isType('string'), $this->isType('array'), $this->isType('object'), $this->isType('callable'), $this->isType('iterable'), $this->isType('null'))->willReturn('r');
        $dep->method('c')->with($this->isType('numeric'), $this->isType('scalar'), $this->isType('resource'), $this->isType('resource (closed)'))->willReturn('r');
        $dep->method('d')->with($this->logicalNot($this->isType('integer')), $this->logicalNot($this->isType('numeric')))->willReturn('r');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->allows('a')->with(\JMac\Testing\Matching\Argument::type('int'), \JMac\Testing\Matching\Argument::type('int'), \JMac\Testing\Matching\Argument::type('bool'), \JMac\Testing\Matching\Argument::type('float'), \JMac\Testing\Matching\Argument::type('float'), \JMac\Testing\Matching\Argument::remaining())->returns('r');
        $dep->allows('b')->with(\JMac\Testing\Matching\Argument::type('string'), \JMac\Testing\Matching\Argument::type('array'), \JMac\Testing\Matching\Argument::type('object'), \JMac\Testing\Matching\Argument::type('callable'), \JMac\Testing\Matching\Argument::type('iterable'), \JMac\Testing\Matching\Argument::type('null'), \JMac\Testing\Matching\Argument::remaining())->returns('r');
        $dep->allows('c')->with(\JMac\Testing\Matching\Argument::satisfies(fn($value) => is_numeric($value)), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_scalar($value)), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_resource($value) || gettype($value) === 'resource (closed)'), \JMac\Testing\Matching\Argument::satisfies(fn($value) => gettype($value) === 'resource (closed)'), \JMac\Testing\Matching\Argument::remaining())->returns('r');
        $dep->allows('d')->with(\JMac\Testing\Matching\Argument::not()->type('int'), \JMac\Testing\Matching\Argument::not()->satisfies(fn($value) => is_numeric($value)), \JMac\Testing\Matching\Argument::remaining())->returns('r');
    }
}
