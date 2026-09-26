<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::string($s)->startsWith('a');
        \Testo\Assert::string($s)->endsWith('z');
        \Testo\Assert::string($s)->ignoringCase()->contains('foo');
        \Testo\Assert::string($s)->ignoringCase()->notContains('bar', 'no bar');
        \Testo\Assert::string($s)->ignoringLineEndings()->ignoringCase()->contains("x\n");
        \Testo\Assert::string($s)->ignoringCase()->ignoringLineEndings()->endsWith("y\n");
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::string($s)->startsWith('a')->endsWith('z');
        \Testo\Assert::string($s)->ignoringCase()->contains('foo')->notContains('bar', 'no bar');
        \Testo\Assert::string($s)->ignoringLineEndings()->ignoringCase()->contains("x\n")->endsWith("y\n");
    }
}
