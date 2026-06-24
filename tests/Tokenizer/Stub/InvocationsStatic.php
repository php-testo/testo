<?php

declare(strict_types=1);

namespace Tests\Tokenizer\Stub;

final class InvocationsStatic
{
    public function doWork(): void
    {
        self::staticHelper();
        static::anotherHelper();
        $this->instanceMethod();
    }

    public static function staticHelper(): void {}

    public static function anotherHelper(): void {}

    public function instanceMethod(): void {}
}
