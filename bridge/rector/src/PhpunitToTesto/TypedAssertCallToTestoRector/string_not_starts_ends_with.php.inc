<?php

class StringNotStartsEndsWithTest extends \PHPUnit\Framework\TestCase
{
    public function test()
    {
        $this->assertStringStartsNotWith('/tmp', $path);
        self::assertStringEndsNotWith('.md', $path, 'not markdown');
    }
}
-----
<?php

class StringNotStartsEndsWithTest extends \PHPUnit\Framework\TestCase
{
    public function test()
    {
        \Testo\Assert::string($path)->notStartsWith('/tmp');
        \Testo\Assert::string($path)->notEndsWith('.md', 'not markdown');
    }
}
