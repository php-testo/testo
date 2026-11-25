<?php

/**
 * Language constructs, adapted from the shipmonk-rnd/turbocov test fixtures (MIT).
 */

declare(strict_types=1);

namespace Testo\CoverageProbe\Constructs;

trait T
{
    public function tm(int $x): int
    {
        return $x
            + 1;
    }
}

class A
{
    use T;

    public function __construct(private int $v = 3)
    {
    }

    public function gen(): \Generator
    {
        for ($i = 0; $i < 3; $i++) {
            $r = yield $i;
            if ($r === 'stop') {
                return 99;
            }
        }
        yield from [7, 8];
    }

    public function m(string $k): string
    {
        return match ($k) {
            'a' => 'A',
            'b' => 'B',
            default => 'D',
        };
    }

    public function tf(bool $throw): int
    {
        try {
            if ($throw) {
                throw new \RuntimeException('x');
            }
            return 1;
        } catch (\RuntimeException $e) {
            return 2;
        } finally {
            $this->v++;
        }
    }
}

class B extends A
{
    public function opt(int $a, int $b = 5,
        int $c = 7): int
    {
        return $a + $b + $c;
    }
}

function script(): int
{
    $b = new B();
    $b->tm(1);
    foreach ($b->gen() as $v) {
        $x = $v;
    }
    $g = $b->gen(); $g->current(); $g->send('stop');
    $b->m('b');
    $b->tf(true);
    $b->opt(1);
    $b->opt(1, 2, 3);
    $f = function (int $x) use ($b) {
        return $x > 1
            ? $b->m('zzz')
            : null;
    };
    $f(2);
    $fib = new \Fiber(function () {
        $y = \Fiber::suspend(1);
        return $y;
    });
    $fib->start();
    $fib->resume(2);
    $arrow = fn ($q) => $q
        ?? 'none';
    $arrow(null);
    $s = 'q';
    switch ($s) { case 'p': $o = 1; break; case 'q': $o = 2; break; }
    $n = null;
    $n?->foo(
        undefined_probe_function()
    );
    static fn () => 1;
    do {
        $o--;
    } while ($o > 0);

    return $o;
}
