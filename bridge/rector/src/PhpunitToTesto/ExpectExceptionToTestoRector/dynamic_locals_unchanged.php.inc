<?php

class DynamicLocals
{
    public function test(array $locals): void
    {
        extract($locals);
        $this->expectException(\RuntimeException::class);
        $message = 'boom';
        $this->expectExceptionMessage($message);
    }
}
