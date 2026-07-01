<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::array($a)->every('is_int');
    }
}
