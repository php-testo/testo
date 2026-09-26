<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::string($s)->same('abc')->notSame('ABC')->ignoringCase()->same('ABC')->notSame('xyz');
        \Testo\Assert::string($t)->ignoringLineEndings()->same("a\nb");
        \Testo\Assert::string($u)->ignoringWhitespace(lineBreaks: true)->same('SELECT id')->notSame('SELECT name');
        \Testo\Assert::string($v)->ignoringWhitespace()->ignoringWhitespace(true)->same('a b');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $this->assertIsString($s);
        $this->assertSame('abc', $s);
        $this->assertNotSame('ABC', $s);
        $this->assertEqualsIgnoringCase('ABC', $s);
        $this->assertNotEqualsIgnoringCase('xyz', $s);
        $this->assertIsString($t);
        $this->assertStringEqualsStringIgnoringLineEndings("a\nb", $t);
        $this->assertIsString($u);
        $this->assertStringEqualsStringIgnoringWhitespace('SELECT id', $u);
        $this->assertStringNotEqualsStringIgnoringWhitespace('SELECT name', $u);
        $this->assertIsString($v);
        $this->assertStringEqualsStringIgnoringWhitespace('a b', $v);
    }
}
