<?php
function pick(int $x): string
{
    $label = match (true) {
        $x > 10 => 'big',
        $x > 0 => 'small',
        default => 'none',
    };
    $map = array(
        'a' => 1,
        'b' => $x,
    );
    return $label . $map['a'];
}
