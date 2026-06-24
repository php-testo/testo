<?php

declare(strict_types=1);

namespace Tests\Tokenizer\Stub;

final class NamedParamClassKeyword
{
    public function foo(string $class): void {}

    public function bar(): void
    {
        $this->foo(class: 'SomeClass');
    }
}
