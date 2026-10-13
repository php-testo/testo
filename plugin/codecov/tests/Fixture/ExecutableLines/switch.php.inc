<?php
function kind(int $x): string
{
    switch ($x) {
        case 1:
            $r = 'one';
            break;
        case 2:
        case 3:
            $r = 'few';
            break;
        default:
            $r = 'many';
    }
    return $r;
}
