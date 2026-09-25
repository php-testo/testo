<?php

class SomeTest
{
    public function test()
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessageContaining('bad')->withMessageContaining('value');
    }
}
