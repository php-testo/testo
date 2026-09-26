<?php

class IterableUnmappedTest
{
    // PHPUnit throws for a Generator where Testo counts it; allOf() with a class or pseudo-type has no
    // assertContainsOnly* counterpart.
    public function test(iterable $any, \Traversable $stream, array $expected)
    {
        \Testo\Assert::iterable($any)->hasCount(2);
        \Testo\Assert::iterable($stream)->sameSizeAs($expected);
        \Testo\Assert::array($rows)->sameSizeAs($any);
        \Testo\Assert::iterable($items)->allOf(\stdClass::class);
        \Testo\Assert::iterable($items)->allOf('object');
        \Testo\Assert::iterable($items)->allOf($type);
        \Testo\Assert::iterable($items)->every('is_int');
    }
}
