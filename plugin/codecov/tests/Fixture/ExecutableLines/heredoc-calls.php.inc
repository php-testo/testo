<?php
function hd(object $o, array $a, \Closure $fn, string $name): string
{
    $text = <<<TXT
        plain line
        method: {$o->title()}
        static: {$o::make()}
        closure: {$fn($name)}
        array: {$a['key']}
        prop: $o->name and $name
        nested: {$o->child()->label(\strtoupper($name))}
        dollar: ${name}
        last line
        TXT;
    $sql = <<<"SQL"
        SELECT *
        FROM t
        WHERE id = {$o->id()}
        SQL;
    return $text . $sql . <<<EOT
        tail {$o->tail()}
        EOT;
}
function single(object $o): string
{
    return "a {$o->x()} b
        c {$o->y()} d";
}
