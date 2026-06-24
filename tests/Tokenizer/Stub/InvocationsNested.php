<?php

declare(strict_types=1);

namespace Tests\Tokenizer\Stub;

final class InvocationsNested
{
    public function run(): void
    {
        $this->outer($this->inner('value'));
        SomeClass::withNested(OtherClass::nested());
    }

    public function outer(mixed $x): void {}

    public function inner(string $s): mixed { return $s; }
}
