<?php

declare(strict_types=1);

namespace Tests\Tokenizer\Stub;

// A `}` closing nothing inside an interpolated string must not end the class: the method after it
// and the function after the class keep their scopes.
final class BracesInInterpolatedStrings
{
    public function interpolate(int $value): string
    {
        return "{\"v\": $value}";
    }

    public function afterInterpolation(): void {}
}

function afterInterpolatedClass(): void {}
