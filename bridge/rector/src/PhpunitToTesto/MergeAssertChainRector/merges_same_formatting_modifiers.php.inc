<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::string($s)->ignoringWhitespace(lineBreaks: true)->startsWith('SELECT');
        \Testo\Assert::string($s)->ignoringWhitespace(lineBreaks: true)->endsWith('users');
        \Testo\Assert::string($s)->ignoringAnsi()->ignoringBlankLines()->contains('OK');
        \Testo\Assert::string($s)->ignoringBlankLines()->ignoringAnsi()->notContains('FAIL');
        \Testo\Assert::string($s)->ignoringWhitespace(true)->ignoringWhitespace()->contains('a');
        \Testo\Assert::string($s)->ignoringWhitespace()->contains('b');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::string($s)->ignoringWhitespace(lineBreaks: true)->startsWith('SELECT')->endsWith('users');
        \Testo\Assert::string($s)->ignoringAnsi()->ignoringBlankLines()->contains('OK')->notContains('FAIL');
        \Testo\Assert::string($s)->ignoringWhitespace(true)->ignoringWhitespace()->contains('a')->contains('b');
    }
}
