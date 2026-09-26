<?php

class RegularExpressionAssertTest
{
    public function test()
    {
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date);
        $this->assertDoesNotMatchRegularExpression('/\s$/', $date, 'no trailing space');
        self::assertRegExp('/^a/i', $name, 'starts with a');
        static::assertNotRegExp('/^b/', $name);
    }
}
-----
<?php

class RegularExpressionAssertTest
{
    public function test()
    {
        \Testo\Assert::string($date)->matchesRegex('/^\d{4}-\d{2}-\d{2}$/');
        \Testo\Assert::string($date)->notMatchesRegex('/\s$/', 'no trailing space');
        \Testo\Assert::string($name)->matchesRegex('/^a/i', 'starts with a');
        \Testo\Assert::string($name)->notMatchesRegex('/^b/');
    }
}
