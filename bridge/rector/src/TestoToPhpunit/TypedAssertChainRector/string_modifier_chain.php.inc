<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::string($s)->startsWith('a')->ignoringCase()->contains('foo')->notContains('bar')->matchesRegex('/x/')->notMatchesRegex('/y/i');
        \Testo\Assert::string($t)->contains("a\r\n")->ignoringLineEndings()->contains("a\n");
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $this->assertIsString($s);
        $this->assertStringStartsWith('a', $s);
        $this->assertStringContainsStringIgnoringCase('foo', $s);
        $this->assertStringNotContainsStringIgnoringCase('bar', $s);
        $this->assertMatchesRegularExpression('/x/', $s);
        $this->assertDoesNotMatchRegularExpression('/y/i', $s);
        $this->assertIsString($t);
        $this->assertStringContainsString("a\r\n", $t);
        $this->assertStringContainsStringIgnoringLineEndings("a\n", $t);
    }
}
