<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::string($s)->ignoringWhitespace()->contains('a');
        \Testo\Assert::string($s)->ignoringWhitespace(lineBreaks: true)->contains('b');
        \Testo\Assert::string($s)->ignoringWhitespace()->contains('c');
        \Testo\Assert::string($s)->contains('d');
        \Testo\Assert::string($s)->ignoringBlankLines()->contains('e');
        \Testo\Assert::string($s)->ignoringAnsi()->contains('f');
        \Testo\Assert::string($s)->ignoringAnsi()->ignoringCase()->contains('g');
        \Testo\Assert::string($s)->ignoringWhitespace(true)->ignoringCase()->contains('h');
        \Testo\Assert::string($s)->ignoringWhitespace(false)->ignoringCase()->contains('i');
    }
}
