<?php
function arg_1($o, $x, $y, $z)
{
    $o->run(
        $x,
        $y,
        $z,
    );
}
function arg_2($o, $x, $y)
{
    $o->run(
        $x,
        'lit',
        $y,
    );
}
function arg_3($o, $x, $y)
{
    $r = foo(
        $x,
        $y->p,
        $y,
    );
    return $r;
}
function arg_4($x, $y)
{
    return new Bar(
        name: $x,
        other: $y,
    );
}
function arg_5($o, $x, $y)
{
    $o->run(
        $x + 1,
        $y,
        bar($x),
        $x,
    );
}
