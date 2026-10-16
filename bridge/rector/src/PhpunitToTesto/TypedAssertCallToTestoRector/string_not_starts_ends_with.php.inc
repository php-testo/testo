<?php

class StringNotStartsEndsWithTest
{
    public function test()
    {
        $this->assertStringStartsNotWith('/tmp', $path);
        self::assertStringEndsNotWith('.md', $path, 'not markdown');
    }
}
-----
<?php

class StringNotStartsEndsWithTest
{
    public function test()
    {
        (static function (string $prefix, string $string, string $message = ''): void {
            \Testo\Assert::string($string)->notStartsWith($prefix, $message);
        })('/tmp', $path);
        (static function (string $suffix, string $string, string $message = ''): void {
            \Testo\Assert::string($string)->notEndsWith($suffix, $message);
        })('.md', $path, 'not markdown');
    }
}
