<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::callable($handler)->isStatic();
        \Testo\Assert::callable($handler)->notStatic()->hasReturnType('?string');
    }
}
