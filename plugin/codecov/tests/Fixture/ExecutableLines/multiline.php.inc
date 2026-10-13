<?php
function call(object $o): array
{
    $o->method(
        'a',
        $o->other(),
    );
    $value = $o
        ->first()
        ->second();
    return [
        'k' => $value,
        'v' => $o->third(),
    ];
}
