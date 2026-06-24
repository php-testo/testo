<?php

declare(strict_types=1);

namespace Tests\Tokenizer\Stub;

enum Color
{
    case Red;
    case Green;
    case Blue;
}

enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
