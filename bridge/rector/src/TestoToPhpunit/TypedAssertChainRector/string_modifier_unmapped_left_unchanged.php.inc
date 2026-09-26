<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::string($s)->ignoringCase()->startsWith('a');
        \Testo\Assert::string($s)->ignoringCase()->endsWith('z');
        \Testo\Assert::string($s)->ignoringLineEndings()->notContains("a\n");
        \Testo\Assert::string($s)->ignoringLineEndings()->endsWith("z\n");
        \Testo\Assert::string($s)->ignoringLineEndings()->matchesRegex('/^a$/m');
        \Testo\Assert::string($s)->ignoringCase()->ignoringLineEndings()->contains("a\n");
        \Testo\Assert::string($s)->ignoringLineEndings()->contains("a\n")->ignoringCase()->contains('b');
        \Testo\Assert::string($s)->contains('a')->ignoringCase()->startsWith('b');
        \Testo\Assert::string($s)->ignoringWhitespace()->contains('a b');
        \Testo\Assert::string($s)->ignoringWhitespace(lineBreaks: true)->contains('a b');
        \Testo\Assert::string($s)->ignoringBlankLines()->contains("a\nb");
        \Testo\Assert::string($s)->ignoringAnsi()->contains('OK');
        \Testo\Assert::string($s)->ignoringAnsi()->ignoringCase()->contains('ok');
    }
}
