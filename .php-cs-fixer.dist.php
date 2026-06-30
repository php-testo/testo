<?php

declare(strict_types=1);

require_once __DIR__ . '/tools/code-style/vendor/autoload.php';

return \Spiral\CodeStyle\Builder::create()
    ->include(__DIR__ . '/core')
    ->include(__FILE__)
    ->build();
