<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::string($s)->contains('foo')->notContains('bar');
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
    }
}
