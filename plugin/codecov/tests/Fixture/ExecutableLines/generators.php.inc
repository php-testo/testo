<?php
final class Gen
{
    public function pairs(array $map): \Generator
    {
        foreach ($map as $k => $v) {
            yield $k => $v;
        }
    }

    public static function delegated(): iterable
    {
        yield from self::inner();
    }

    private static function inner(): \Generator
    {
        return yield;
    }
}

function outerNotGenerator(): \Closure
{
    $inner = function (): \Generator {
        yield 1;
    };
    return $inner;
}

function earlyReturn(bool $stop): \Generator
{
    if ($stop) {
        return;
    }
    yield 2;
}

function asArgument(): \Generator
{
    $x = \strtoupper(yield 'q');
    echo $x;
}

function multiline(array $rows): \Generator
{
    yield [
        'count' => \count($rows),
        'rows' => $rows,
    ];
}

function arrow(): \Closure
{
    return fn() => yield 1;
}
