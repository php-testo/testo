<?php

class SomeTest
{
    public function test()
    {
        $dep->method('and')->with($this->logicalAnd($this->greaterThan(5), $this->lessThan(9)), $this->logicalXor($this->isTrue(), $this->isNull()))->willReturn('r');
        $dep->method('eq')->with($this->equalToWithDelta(5.0, 0.1), $this->equalToIgnoringCase('Abc'))->willReturn('r');
        $dep->method('canon')->with($this->equalToCanonicalizing([1, 2]))->willReturn('r');
        $dep->method('ci')->with($this->stringContains('x', true), $this->stringContains('y', false))->willReturn('r');
        $dep->method('count')->with($this->countOf(2), $this->isList(), $this->isJson())->willReturn('r');
        $dep->method('float')->with($this->isNan(), $this->isFinite(), $this->isInfinite())->willReturn('r');
        $dep->method('file')->with($this->fileExists(), $this->directoryExists(), $this->isReadable(), $this->isWritable())->willReturn('r');
        $dep->method('items')->with($this->containsIdentical(1), $this->containsEqual(2), $this->containsOnly('string'), $this->containsOnlyInstancesOf(Foo::class))->willReturn('r');
        $dep->method('obj')->with($this->objectEquals($expected))->willReturn('r');
        $dep->method('or')->with($this->logicalOr($this->isNull(), $this->logicalAnd($this->greaterThan(1), $this->lessThan(3))))->willReturn('r');
        $dep->method('not')->with($this->logicalNot($this->anything()), $this->logicalNot($this->logicalAnd($this->isTrue(), $this->isFalse())))->willReturn('r');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->allows('and')->with(\JMac\Testing\Matching\Argument::satisfies(fn($value) => $value > 5 && $value < 9), \JMac\Testing\Matching\Argument::satisfies(fn($value) => $value === true xor $value === null), \JMac\Testing\Matching\Argument::remaining())->returns('r');
        $dep->allows('eq')->with(\JMac\Testing\Matching\Argument::satisfies(fn($value) => is_numeric($value) ? abs($value - 5.0) <= 0.1 : $value == 5.0), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_bool($value) ? $value == 'Abc' : (is_scalar($value) || $value === null) && mb_strtolower((string) $value) == mb_strtolower((string) 'Abc')), \JMac\Testing\Matching\Argument::remaining())->returns('r');
        $dep->allows('canon')->with(\JMac\Testing\Matching\Argument::satisfies(fn($value) => (static function ($actual, $expected): bool {
            if (is_array($actual) !== is_array($expected)) {
                return false;
            }
            if (is_array($actual)) {
                sort($actual);
                sort($expected);
            }
            return $actual == $expected;
        })($value, [1, 2])), \JMac\Testing\Matching\Argument::remaining())->returns('r');
        $dep->allows('ci')->with(\JMac\Testing\Matching\Argument::satisfies(fn($value) => is_string($value) && mb_stripos($value, 'x') !== false), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_string($value) && str_contains($value, 'y')), \JMac\Testing\Matching\Argument::remaining())->returns('r');
        $dep->allows('count')->with(\JMac\Testing\Matching\Argument::satisfies(fn($value) => is_countable($value) && count($value) === 2), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_array($value) && array_is_list($value)), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_string($value) && (json_decode($value) !== null || json_last_error() === JSON_ERROR_NONE)), \JMac\Testing\Matching\Argument::remaining())->returns('r');
        $dep->allows('float')->with(\JMac\Testing\Matching\Argument::satisfies(fn($value) => (is_float($value) || is_int($value)) && is_nan($value)), \JMac\Testing\Matching\Argument::satisfies(fn($value) => (is_float($value) || is_int($value)) && is_finite($value)), \JMac\Testing\Matching\Argument::satisfies(fn($value) => (is_float($value) || is_int($value)) && is_infinite($value)), \JMac\Testing\Matching\Argument::remaining())->returns('r');
        $dep->allows('file')->with(\JMac\Testing\Matching\Argument::satisfies(fn($value) => is_string($value) && file_exists($value)), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_string($value) && is_dir($value)), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_string($value) && is_readable($value)), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_string($value) && is_writable($value)), \JMac\Testing\Matching\Argument::remaining())->returns('r');
        $dep->allows('items')->with(\JMac\Testing\Matching\Argument::contains(\JMac\Testing\Matching\Argument::same(1)), \JMac\Testing\Matching\Argument::contains(2), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_iterable($value) && array_filter([...$value], fn($valueItem) => !is_string($valueItem)) === []), \JMac\Testing\Matching\Argument::satisfies(fn($value) => is_iterable($value) && array_filter([...$value], fn($valueItem) => !$valueItem instanceof Foo) === []), \JMac\Testing\Matching\Argument::remaining())->returns('r');
        $dep->allows('obj')->with(\JMac\Testing\Matching\Argument::satisfies(fn($value) => is_object($value) && $value->equals($expected)), \JMac\Testing\Matching\Argument::remaining())->returns('r');
        $dep->allows('or')->with(\JMac\Testing\Matching\Argument::any(null, \JMac\Testing\Matching\Argument::satisfies(fn($value) => $value > 1 && $value < 3)), \JMac\Testing\Matching\Argument::remaining())->returns('r');
        $dep->allows('not')->with(\JMac\Testing\Matching\Argument::satisfies(fn($value) => !true), \JMac\Testing\Matching\Argument::not()->satisfies(fn($value) => $value === true && $value === false), \JMac\Testing\Matching\Argument::remaining())->returns('r');
    }
}
