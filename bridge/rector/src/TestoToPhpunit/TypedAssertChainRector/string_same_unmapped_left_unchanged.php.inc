<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::string($s)->ignoringLineEndings()->notSame("a\nb");
        \Testo\Assert::string($s)->ignoringWhitespace()->same('a b');
        \Testo\Assert::string($s)->ignoringWhitespace(false)->same('a b');
        \Testo\Assert::string($s)->ignoringWhitespace($flag)->same('a b');
        \Testo\Assert::string($s)->ignoringWhitespace(true)->ignoringWhitespace()->same('a b');
        \Testo\Assert::string($s)->ignoringWhitespace(true)->contains('a b');
        \Testo\Assert::string($s)->ignoringBlankLines()->same("a\nb");
        \Testo\Assert::string($s)->ignoringAnsi()->same('OK');
        \Testo\Assert::string($s)->ignoringCase()->ignoringLineEndings()->same("a\nb");
        \Testo\Assert::string($s)->ignoringCase()->ignoringWhitespace(true)->same('a b');
    }
}
