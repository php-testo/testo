<?php
function jt_m2s($v)
{
    return match ($v) {
        'a' => 1,
        'b' => 2,
    };
}
function jt_m1s($v)
{
    return match ($v) {
        'a' => 1,
        default => 2,
    };
}
function jt_m4i($v)
{
    return match ($v) {
        1 => 'a',
        2 => 'b',
        3 => 'c',
        4 => 'd',
    };
}
function jt_m5i($v)
{
    return match ($v) {
        1 => 'a',
        2 => 'b',
        3 => 'c',
        4 => 'd',
        5 => 'e',
    };
}
function jt_m2sc($v)
{
    return match ($v) {
        'a', 'b' => 1,
        'c' => 2,
    };
}
function jt_mconst($v)
{
    return match ($v) {
        Foo::A => 1,
        Foo::B => 2,
    };
}
function jt_s2s($v)
{
    switch ($v) {
        case 'a':
            return 1;
        case 'b':
            return 2;
    }
    return 0;
}
function jt_s4i($v)
{
    switch ($v) {
        case 1:
            return 1;
        case 2:
            return 2;
        case 3:
            return 3;
        case 4:
            return 4;
    }
    return 0;
}
function jt_s5i($v)
{
    switch ($v) {
        case 1:
            return 1;
        case 2:
            return 2;
        case 3:
            return 3;
        case 4:
            return 4;
        case 5:
            return 5;
    }
    return 0;
}
function jt_mprop($o)
{
    return match ($o->kind) {
        'a' => 1,
        'b' => 2,
    };
}
