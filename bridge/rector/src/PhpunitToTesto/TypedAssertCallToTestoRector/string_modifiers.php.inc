<?php

class StringModifierAssertTest
{
    public function test(string $out, string $html, int $count, $unknown)
    {
        $this->assertStringContainsStringIgnoringCase('<title>', $html);
        $this->assertStringNotContainsStringIgnoringCase('error', $out, 'no errors');
        self::assertStringContainsStringIgnoringLineEndings("done\n", $out);
        $this->assertStringEqualsStringIgnoringLineEndings("a\nb", $out, 'lines');
        $this->assertStringEqualsStringIgnoringWhitespace('SELECT id FROM users', $out);
        $this->assertStringNotEqualsStringIgnoringWhitespace('a b', $out);
        $this->assertEqualsIgnoringCase('abc', $out);
        $this->assertNotEqualsIgnoringCase($html, $out, 'differ');
        $this->assertEqualsIgnoringCase('3', $count);
        $this->assertEqualsIgnoringCase('abc', $unknown);
        $this->assertNotEqualsIgnoringCase(['a'], $out);
        $this->assertStringEqualsFileIgnoringCase('expected.txt', $out);
    }
}
-----
<?php

class StringModifierAssertTest
{
    public function test(string $out, string $html, int $count, $unknown)
    {
        \Testo\Assert::string($html)->ignoringCase()->contains('<title>');
        \Testo\Assert::string($out)->ignoringCase()->notContains('error', 'no errors');
        \Testo\Assert::string($out)->ignoringLineEndings()->contains("done\n");
        \Testo\Assert::string($out)->ignoringLineEndings()->same("a\nb", 'lines');
        \Testo\Assert::string($out)->ignoringWhitespace(lineBreaks: true)->same('SELECT id FROM users');
        \Testo\Assert::string($out)->ignoringWhitespace(lineBreaks: true)->notSame('a b');
        \Testo\Assert::string($out)->ignoringCase()->same('abc');
        \Testo\Assert::string($out)->ignoringCase()->notSame($html, 'differ');
        $this->assertEqualsIgnoringCase('3', $count);
        $this->assertEqualsIgnoringCase('abc', $unknown);
        $this->assertNotEqualsIgnoringCase(['a'], $out);
        $this->assertStringEqualsFileIgnoringCase('expected.txt', $out);
    }
}
