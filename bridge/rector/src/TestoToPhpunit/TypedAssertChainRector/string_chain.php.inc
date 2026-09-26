<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::string($s)->contains('foo')->notContains('bar')->startsWith('f')->endsWith('o');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $this->assertIsString($s);
        $this->assertStringContainsString('foo', $s);
        $this->assertStringNotContainsString('bar', $s);
        $this->assertStringStartsWith('f', $s);
        $this->assertStringEndsWith('o', $s);
    }
}
