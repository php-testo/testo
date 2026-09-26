<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::string($s)->contains('strict');
        \Testo\Assert::string($s)->ignoringCase()->contains('case');
        \Testo\Assert::string($s)->contains('strict again');
        \Testo\Assert::string($s)->ignoringLineEndings()->contains("lines\n");
        \Testo\Assert::string($s)->ignoringCase()->contains('case again');
        \Testo\Assert::string($s)->ignoringCase()->ignoringLineEndings()->contains("both\n");
        \Testo\Assert::string($s)->ignoringLineEndings()->endsWith("\n");
    }
}
