<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::string($s)->ignoringCase()->same('abc');
        \Testo\Assert::string($s)->ignoringCase()->notSame('xyz');
        \Testo\Assert::string($s)->same('Abc');
        \Testo\Assert::string($s)->notSame('abc');
        \Testo\Assert::string($s)->ignoringLineEndings()->same("a\n");
        \Testo\Assert::string($s)->same("a\r\n");
        \Testo\Assert::string($s)->ignoringWhitespace(lineBreaks: true)->same('a b');
        \Testo\Assert::string($s)->ignoringWhitespace()->notSame('a b');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::string($s)->ignoringCase()->same('abc')->notSame('xyz');
        \Testo\Assert::string($s)->same('Abc')->notSame('abc');
        \Testo\Assert::string($s)->ignoringLineEndings()->same("a\n");
        \Testo\Assert::string($s)->same("a\r\n");
        \Testo\Assert::string($s)->ignoringWhitespace(lineBreaks: true)->same('a b');
        \Testo\Assert::string($s)->ignoringWhitespace()->notSame('a b');
    }
}
