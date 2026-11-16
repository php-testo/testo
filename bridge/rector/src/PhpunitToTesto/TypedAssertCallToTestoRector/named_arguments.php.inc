<?php

class NamedTypedAssertionsTest extends \PHPUnit\Framework\TestCase
{
    public function test($s, $n, $row, $rows, $x)
    {
        $this->assertStringContainsString(needle: 'x', haystack: $s);
        $this->assertStringContainsString(haystack: $s, needle: 'x', message: 'contains');
        $this->assertStringStartsWith(message: 'prefix', prefix: 'x', string: $s);
        $this->assertStringEqualsStringIgnoringWhitespace(actual: $s, expected: 'a b');
        $this->assertMatchesRegularExpression(string: $s, pattern: '/x/');
        $this->assertRegExp(string: $s, pattern: '/x/');
        $this->assertGreaterThan(actual: $n, minimum: 0);
        $this->assertArrayHasKey(array: $row, key: 'id');
        $this->assertIsList(array: $rows);
        $this->assertIsString(actual: $x);
        \PHPUnit\Framework\assertStringEndsWith(string: $s, suffix: '.md');
    }
}
-----
<?php

class NamedTypedAssertionsTest extends \PHPUnit\Framework\TestCase
{
    public function test($s, $n, $row, $rows, $x)
    {
        \Testo\Assert::string($s)->contains('x');
        \Testo\Assert::string($s)->contains('x', 'contains');
        \Testo\Assert::string($s)->startsWith('x', 'prefix');
        \Testo\Assert::string($s)->ignoringWhitespace(lineBreaks: true)->same('a b');
        \Testo\Assert::string($s)->matchesRegex('/x/');
        \Testo\Assert::string($s)->matchesRegex('/x/');
        \Testo\Assert::numeric($n)->greaterThan(0);
        \Testo\Assert::array($row)->hasKeys('id');
        \Testo\Assert::array($rows)->isList();
        \Testo\Assert::string($x);
        \Testo\Assert::string($s)->endsWith('.md');
    }
}
