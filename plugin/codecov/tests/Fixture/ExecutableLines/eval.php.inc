<?php
function run_eval(string $code): mixed
{
    $result = eval($code);
    return $result;
}
