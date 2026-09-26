<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::string($s)->contains('a');
        \Testo\Assert::string($s)->contains('b')->ignoringCase()->contains('c');
        \Testo\Assert::string($s)->ignoringCase()->contains('d');
        \Testo\Assert::string($s)->ignoringCase()->contains('e')->ignoringLineEndings()->endsWith("f\n");
        \Testo\Assert::string($s)->ignoringCase()->contains('g');
    }
}
