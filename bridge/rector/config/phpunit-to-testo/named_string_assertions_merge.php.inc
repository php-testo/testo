<?php

final class NamedStringAssertionsTest extends \PHPUnit\Framework\TestCase
{
    public function testOutput(): void
    {
        $out = \render();
        $this->assertStringContainsString('a', $out);
        $this->assertStringContainsString(haystack: $out, needle: 'b');
        $this->assertStringNotContainsString(needle: 'c', haystack: $out, message: 'no c');
        $this->assertSame(actual: $out, expected: 'abd');
    }
}
-----
<?php

final class NamedStringAssertionsTest
{
    #[\Testo\Test]
    public function testOutput(): void
    {
        $out = \render();
        \Testo\Assert::string($out)->contains('a')->contains('b')->notContains('c', 'no c');
        \Testo\Assert::same($out, 'abd');
    }
}
