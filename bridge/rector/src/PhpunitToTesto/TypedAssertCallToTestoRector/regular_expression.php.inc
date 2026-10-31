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
        (static function (string $pattern, string $string, string $message = ''): void {
            \Testo\Assert::string($string)->matchesRegex($pattern, $message);
        })('/^\d{4}-\d{2}-\d{2}$/', $date);
        (static function (string $pattern, string $string, string $message = ''): void {
            \Testo\Assert::string($string)->notMatchesRegex($pattern, $message);
        })('/\s$/', $date, 'no trailing space');
        (static function (string $pattern, string $string, string $message = ''): void {
            \Testo\Assert::string($string)->matchesRegex($pattern, $message);
        })('/^a/i', $name, 'starts with a');
        (static function (string $pattern, string $string, string $message = ''): void {
            \Testo\Assert::string($string)->notMatchesRegex($pattern, $message);
        })('/^b/', $name);
    }
}
