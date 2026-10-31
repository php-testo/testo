<?php

class ContainsListPropertyTest
{
    public function test()
    {
        $this->assertStringContainsString('needle', $haystack);
        $this->assertStringNotContainsString('error', $output, 'no errors');
        $this->assertNotContains($removed, $items);
        self::assertNotContains('x', $letters, 'x is gone');
        $this->assertObjectHasProperty('id', $entity, 'has an id');
        $this->assertIsList($rows);
        $this->assertIsList($rows, 'sequential keys');
    }

    public function namedArguments(string $needle, string $haystack): void
    {
        $this->assertStringContainsString(needle: 'needle', haystack: 'haystack');
        $this->assertStringContainsString(haystack: $haystack, needle: $needle);
    }
}
-----
<?php

class ContainsListPropertyTest
{
    public function test()
    {
        (static function (string $needle, string $haystack, string $message = ''): void {
            \Testo\Assert::string($haystack)->contains($needle, $message);
        })('needle', $haystack);
        (static function (string $needle, string $haystack, string $message = ''): void {
            \Testo\Assert::string($haystack)->notContains($needle, $message);
        })('error', $output, 'no errors');
        \Testo\Assert::iterable($items)->notContains($removed);
        \Testo\Assert::iterable($letters)->notContains('x', 'x is gone');
        \Testo\Assert::object($entity)->hasProperty('id', 'has an id');
        \Testo\Assert::array($rows)->isList();
        \Testo\Assert::array($rows)->isList('sequential keys');
    }

    public function namedArguments(string $needle, string $haystack): void
    {
        \Testo\Assert::string('haystack')->contains('needle');
        \Testo\Assert::string($haystack)->contains($needle);
    }
}
