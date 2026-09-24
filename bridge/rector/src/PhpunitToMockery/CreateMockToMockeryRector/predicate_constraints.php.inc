<?php

class SomeTest
{
    public function test()
    {
        $dep->method('n')->with($this->greaterThan(5), $this->lessThan(9), $this->greaterThanOrEqual(1), $this->lessThanOrEqual(3))->willReturn('u');
        $dep->method('s')->with($this->stringContains('x'), $this->stringStartsWith('a'), $this->stringEndsWith('z'), $this->isEmpty())->willReturn('t');
        $dep->method('not')->with($this->logicalNot($this->equalTo('a')), $this->logicalNot($this->isType('int')), $this->logicalNot($this->isInstanceOf(Foo::class)))->willReturn('r');
        $dep->method('or')->with($this->logicalOr($this->equalTo('a'), 'b', $this->isNull()))->willReturn('r');
        $dep->method('and')->with($this->logicalAnd($this->greaterThan(5), $this->lessThan(9)))->willReturn('r');
        $dep->method('xor')->with($this->logicalXor($this->isTrue(), $this->isNull()))->willReturn('r');
        $dep->method('nested')->with($this->logicalOr($this->callback(fn ($x) => $x === 1), $this->matchesRegularExpression('/^a/')))->willReturn('r');
        $dep->method('key')->with($this->logicalNot($this->arrayHasKey('k')))->willReturn('r');
        $dep->method('class')->with($this->logicalOr($this->isInstanceOf($class), 1))->willReturn('r');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->shouldReceive('n')->with(\Mockery::on(fn($value) => $value > 5), \Mockery::on(fn($value) => $value < 9), \Mockery::on(fn($value) => $value >= 1), \Mockery::on(fn($value) => $value <= 3))->andReturn('u');
        $dep->shouldReceive('s')->with(\Mockery::on(fn($value) => is_string($value) && str_contains($value, 'x')), \Mockery::on(fn($value) => is_string($value) && str_starts_with($value, 'a')), \Mockery::on(fn($value) => is_string($value) && str_ends_with($value, 'z')), \Mockery::on(fn($value) => $value instanceof \Countable ? count($value) === 0 : empty($value)))->andReturn('t');
        $dep->shouldReceive('not')->with(\Mockery::on(fn($value) => !($value == 'a')), \Mockery::on(fn($value) => !is_int($value)), \Mockery::on(fn($value) => !$value instanceof Foo))->andReturn('r');
        $dep->shouldReceive('or')->with(\Mockery::on(fn($value) => $value == 'a' || $value == 'b' || $value === null))->andReturn('r');
        $dep->shouldReceive('and')->with(\Mockery::on(fn($value) => $value > 5 && $value < 9))->andReturn('r');
        $dep->shouldReceive('xor')->with(\Mockery::on(fn($value) => $value === true xor $value === null))->andReturn('r');
        $dep->shouldReceive('nested')->with(\Mockery::on(fn($value) => (fn ($x) => $x === 1)($value) || is_string($value) && preg_match('/^a/', $value) > 0))->andReturn('r');
        $dep->shouldReceive('key')->with(\Mockery::on(fn($value) => !(is_array($value) ? array_key_exists('k', $value) : $value instanceof \ArrayAccess && $value->offsetExists('k'))))->andReturn('r');
        $dep->shouldReceive('class')->with(\Mockery::on(fn($value) => $value instanceof $class || $value == 1))->andReturn('r');
    }
}
