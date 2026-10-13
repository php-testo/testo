<?php
function constArrayArg($o): void
{
    $o->run(
        'x',
        ['a' => 1],
    );
}
function emptyReturn(): array
{
    return [];
}
function altSwitch(int $x): string
{
    switch ($x):
        case 1:
            return 'one';
        default:
            return 'other';
    endswitch;
}
function afterClosure($o, array $items): void
{
    $o->map(
        function ($a) {
            return $a;
        },
        $items,
    );
    $o->map(
        fn($a) => $a,
        $items,
    );
}
function afterConstants($o, $x): void
{
    $o->run(
        \PHP_EOL,
        $x,
    );
    $o->run(
        Foo::BAR,
        $x,
    );
}
