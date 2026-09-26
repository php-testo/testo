<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::string($s)->notStartsWith('/tmp')->notEndsWith('.md');
        \Testo\Assert::string($s)->ignoringCase()->notStartsWith('/TMP');
        \Testo\Assert::string($s)->ignoringLineEndings()->notEndsWith("\n");
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $this->assertIsString($s);
        $this->assertStringStartsNotWith('/tmp', $s);
        $this->assertStringEndsNotWith('.md', $s);
        \Testo\Assert::string($s)->ignoringCase()->notStartsWith('/TMP');
        \Testo\Assert::string($s)->ignoringLineEndings()->notEndsWith("\n");
    }
}
