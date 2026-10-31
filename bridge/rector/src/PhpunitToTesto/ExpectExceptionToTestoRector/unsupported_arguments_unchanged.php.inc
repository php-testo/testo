<?php

class UnsupportedExpectationArguments
{
    public function test(): void
    {
        $this->expectException(wrong: \RuntimeException::class);
        $this->expectException(...$arguments);
        $this->expectException(\RuntimeException::class, 'extra');
        $this->expectException(...);
    }
}
