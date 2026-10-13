<?php
function misc(?object $o): mixed
{
    static $count = 0;
    global $config;
    $count++;
    [$a, $b] = [1, 2];
    list('x' => $x) = ['x' => 3];
    $f = strlen(...);
    $g = fn(int $v): int => $v * 2;
    $h = static function () use ($a): int {
        return $a;
    };
    $name = $o?->name;
    unset($a);
    print 'p';
    echo $b, $x;
    isset($o) or throw new \LogicException();
    empty($b);
    goto end;
    end:
    return [$f, $g, $h, $name];
}
