<?php

class SomeTest
{
    public function test()
    {
        $mock->shouldReceive('contains')->with(\Mockery::contains(1, 'two'))->andReturn(1);
        $mock->shouldReceive('subset')->with(\Mockery::subset(['a' => 1]), \Mockery::subset(['b' => 2], false))->andReturn(1);
        $mock->shouldReceive('duck')->with(\Mockery::ducktype('run', 'stop'))->andReturn(1);
        $mock->shouldReceive('alias')->with(\Mockery::type('integer'), \Mockery::type('long'), \Mockery::type('double'), \Mockery::type('real'), \Mockery::type('Bool'))->andReturn(1);
        $mock->shouldReceive('native')->with(\Mockery::type('array'), \Mockery::type('object'), \Mockery::type('callable'), \Mockery::type('iterable'), \Mockery::type('null'), \Mockery::type('float'))->andReturn(1);
        $mock->shouldReceive('predicate')->with(\Mockery::type('numeric'), \Mockery::type('scalar'), \Mockery::type('resource'), \Mockery::type('countable'))->andReturn(1);
        $mock->shouldReceive('float')->with(\Mockery::type('finite'), \Mockery::type('infinite'), \Mockery::type('nan'))->andReturn(1);
        $mock->shouldReceive('class')->with(\Mockery::type(Foo::class), \Mockery::type('App\Foo'), \Mockery::type($type))->andReturn(1);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $mock->allows('contains')->with(\JMac\Testing\Matching\Argument::satisfies(fn($value) => is_array($value) && in_array(1, $value) && in_array('two', $value)))->returns(1);
        $mock->allows('subset')->with(\JMac\Testing\Matching\Argument::satisfies(fn($value) => is_array($value) && array_replace_recursive($value, ['a' => 1]) === $value), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_array($value) && array_replace_recursive($value, ['b' => 2]) == $value))->returns(1);
        $mock->allows('duck')->with(\JMac\Testing\Matching\Argument::satisfies(fn($value) => is_object($value) && method_exists($value, 'run') && method_exists($value, 'stop')))->returns(1);
        $mock->allows('alias')->with(\JMac\Testing\Matching\Argument::type('int'), \JMac\Testing\Matching\Argument::type('int'), \JMac\Testing\Matching\Argument::type('float'), \JMac\Testing\Matching\Argument::type('float'), \JMac\Testing\Matching\Argument::type('bool'))->returns(1);
        $mock->allows('native')->with(\JMac\Testing\Matching\Argument::type('array'), \JMac\Testing\Matching\Argument::type('object'), \JMac\Testing\Matching\Argument::type('callable'), \JMac\Testing\Matching\Argument::type('iterable'), \JMac\Testing\Matching\Argument::type('null'), \JMac\Testing\Matching\Argument::type('float'))->returns(1);
        $mock->allows('predicate')->with(\JMac\Testing\Matching\Argument::satisfies(fn($value) => is_numeric($value)), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_scalar($value)), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_resource($value)), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_countable($value)))->returns(1);
        $mock->allows('float')->with(\JMac\Testing\Matching\Argument::satisfies(fn($value) => is_finite($value)), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_infinite($value)), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_nan($value)))->returns(1);
        $mock->allows('class')->with(\JMac\Testing\Matching\Argument::type(Foo::class), \JMac\Testing\Matching\Argument::type('App\Foo'), \JMac\Testing\Matching\Argument::type($type))->returns(1);
    }
}
