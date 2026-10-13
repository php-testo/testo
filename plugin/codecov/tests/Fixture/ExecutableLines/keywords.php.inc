<?php
function kw_run($x)
{
    $a = kw_call(class: $x);
    if ($a) {
        $c = 2;
    }
    $d = kw_call(function: $x);
    if ($d) {
        $e = 4;
    }
    $f = kw_call(interface: $x, trait: 1, enum: 2);
    if ($f) {
        $g = 5;
    }
    return $c + $e + $g;
}

final class KwNamed
{
    public function class(int $x): int
    {
        return $x;
    }

    public function function(int $x): int
    {
        return $x;
    }

    public static function trait(): int
    {
        return self::trait() + KwNamed::interface;
    }

    public function &enum(): array
    {
        return $this->items;
    }
}
