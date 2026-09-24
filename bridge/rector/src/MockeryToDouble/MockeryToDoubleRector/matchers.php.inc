<?php

use Mockery;

class SomeTest
{
    public function test()
    {
        $mock->shouldReceive('a')->with(Mockery::any(), Mockery::type('string'), Mockery::on(fn ($v) => $v > 1))->andReturn(1);
        $mock->shouldReceive('b')->with(Mockery::capture($captured), Mockery::pattern('/^\d+$/'))->andReturn(1);
        $mock->shouldReceive('c')->with(Mockery::anyOf(1, 2), Mockery::notAnyOf(3, 4), Mockery::not(5))->andReturn(1);
        $mock->shouldReceive('d')->with(Mockery::isSame($object), Mockery::mustBe($value), Mockery::isEqual($loose))->andReturn(1);
        $mock->shouldReceive('e')->with(Mockery::hasValue('v'), Mockery::contains('c'))->andReturn(1);
        $mock->shouldReceive('f')->with(Mockery::hasKey('k'))->andReturn(1);
        $mock->shouldReceive('g')->with('first', Mockery::andAnyOtherArgs())->andReturn(1);
        $mock->shouldReceive('h')->with(Mockery::andAnyOthers())->andReturn(1);
    }
}
-----
<?php

use Mockery;

class SomeTest
{
    public function test()
    {
        $mock->allows('a')->with(\JMac\Testing\Matching\Argument::any(), \JMac\Testing\Matching\Argument::type('string'), \JMac\Testing\Matching\Argument::satisfies(fn ($v) => $v > 1))->returns(1);
        $mock->allows('b')->with(\JMac\Testing\Matching\Argument::capture($captured), \JMac\Testing\Matching\Argument::matches('/^\d+$/'))->returns(1);
        $mock->allows('c')->with(\JMac\Testing\Matching\Argument::any(1, 2), \JMac\Testing\Matching\Argument::not()->any(3, 4), \JMac\Testing\Matching\Argument::not(5))->returns(1);
        $mock->allows('d')->with(\JMac\Testing\Matching\Argument::same($object), $value, $loose)->returns(1);
        $mock->allows('e')->with(\JMac\Testing\Matching\Argument::contains('v'), \JMac\Testing\Matching\Argument::contains('c'))->returns(1);
        $mock->allows('f')->with(\JMac\Testing\Matching\Argument::satisfies(fn($value) => (is_array($value) || $value instanceof \ArrayAccess) && array_key_exists('k', (array) $value)))->returns(1);
        $mock->allows('g')->with('first', \JMac\Testing\Matching\Argument::remaining())->returns(1);
        $mock->allows('h')->with(\JMac\Testing\Matching\Argument::remaining())->returns(1);
    }
}
