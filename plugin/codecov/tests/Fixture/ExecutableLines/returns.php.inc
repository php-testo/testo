<?php
function ret_a()
{
    return [
        'a' => 1,
    ];
}
function ret_b($x)
{
    return [
        'a' => $x,
    ];
}
function ret_c($x)
{
    return match (true) {
        $x => 1,
        default => 2,
    };
}
function ret_d($x)
{
    return $x
        ? 1
        : 2;
}
function ret_e($x)
{
    return foo(
        $x,
    );
}
function ret_f($x)
{
    $y = [
        'a' => $x,
    ];
    return $y;
}
function ret_g($x)
{
    $y = match (true) {
        $x => 1,
        default => 2,
    };
    return $y;
}
function ret_h($x)
{
    return new Foo(
        $x,
    );
}
