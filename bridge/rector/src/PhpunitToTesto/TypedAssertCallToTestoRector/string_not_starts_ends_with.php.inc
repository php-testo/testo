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
        \Testo\Assert::string($path)->notStartsWith('/tmp');
        \Testo\Assert::string($path)->notEndsWith('.md', 'not markdown');
    }
}
