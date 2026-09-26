<?php

class SomeTest
{
    public function test()
    {
        $dep->method('eq')->with($this->equalToWithDelta(5.0, 0.1), $this->equalToIgnoringCase('Abc'))->willReturn('r');
        $dep->method('canon')->with($this->equalToCanonicalizing([1, 2]))->willReturn('r');
        $dep->method('ci')->with($this->stringContains('x', true), $this->stringContains('y', false))->willReturn('r');
        $dep->method('count')->with($this->countOf(2), $this->isList(), $this->isJson())->willReturn('r');
        $dep->method('float')->with($this->isNan(), $this->isFinite(), $this->isInfinite())->willReturn('r');
        $dep->method('file')->with($this->fileExists(), $this->directoryExists(), $this->isReadable(), $this->isWritable())->willReturn('r');
        $dep->method('items')->with($this->containsIdentical(1), $this->containsOnly('string'), $this->containsOnlyInstancesOf(Foo::class))->willReturn('r');
        $dep->method('obj')->with($this->objectEquals($expected), $this->objectEquals($expected, 'sameAs'))->willReturn('r');
        $dep->method('closed')->with($this->isType('resource (closed)'))->willReturn('r');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->shouldReceive('eq')->with(\Mockery::on(fn($value) => is_numeric($value) ? abs($value - 5.0) <= 0.1 : $value == 5.0), \Mockery::on(fn($value) => is_bool($value) ? $value == 'Abc' : (is_scalar($value) || $value === null) && mb_strtolower((string) $value) == mb_strtolower((string) 'Abc')), \Mockery::andAnyOtherArgs())->andReturn('r');
        $dep->shouldReceive('canon')->with(\Mockery::on(fn($value) => (static function ($actual, $expected): bool {
            if (is_array($actual) !== is_array($expected)) {
                return false;
            }
            if (is_array($actual)) {
                sort($actual);
                sort($expected);
            }
            return $actual == $expected;
        })($value, [1, 2])), \Mockery::andAnyOtherArgs())->andReturn('r');
        $dep->shouldReceive('ci')->with(\Mockery::on(fn($value) => is_string($value) && mb_stripos($value, 'x') !== false), \Mockery::on(fn($value) => is_string($value) && str_contains($value, 'y')), \Mockery::andAnyOtherArgs())->andReturn('r');
        $dep->shouldReceive('count')->with(\Mockery::on(fn($value) => is_countable($value) && count($value) === 2), \Mockery::on(fn($value) => is_array($value) && array_is_list($value)), \Mockery::on(fn($value) => is_string($value) && (json_decode($value) !== null || json_last_error() === JSON_ERROR_NONE)), \Mockery::andAnyOtherArgs())->andReturn('r');
        $dep->shouldReceive('float')->with(\Mockery::on(fn($value) => (is_float($value) || is_int($value)) && is_nan($value)), \Mockery::on(fn($value) => (is_float($value) || is_int($value)) && is_finite($value)), \Mockery::on(fn($value) => (is_float($value) || is_int($value)) && is_infinite($value)), \Mockery::andAnyOtherArgs())->andReturn('r');
        $dep->shouldReceive('file')->with(\Mockery::on(fn($value) => is_string($value) && file_exists($value)), \Mockery::on(fn($value) => is_string($value) && is_dir($value)), \Mockery::on(fn($value) => is_string($value) && is_readable($value)), \Mockery::on(fn($value) => is_string($value) && is_writable($value)), \Mockery::andAnyOtherArgs())->andReturn('r');
        $dep->shouldReceive('items')->with(\Mockery::on(fn($value) => is_iterable($value) && in_array(1, [...$value], true)), \Mockery::on(fn($value) => is_iterable($value) && array_filter([...$value], fn($valueItem) => !is_string($valueItem)) === []), \Mockery::on(fn($value) => is_iterable($value) && array_filter([...$value], fn($valueItem) => !$valueItem instanceof Foo) === []), \Mockery::andAnyOtherArgs())->andReturn('r');
        $dep->shouldReceive('obj')->with(\Mockery::on(fn($value) => is_object($value) && $value->equals($expected)), \Mockery::on(fn($value) => is_object($value) && $value->sameAs($expected)), \Mockery::andAnyOtherArgs())->andReturn('r');
        $dep->shouldReceive('closed')->with(\Mockery::on(fn($value) => gettype($value) === 'resource (closed)'), \Mockery::andAnyOtherArgs())->andReturn('r');
    }
}
