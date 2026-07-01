<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::array($a)->hasKeys('id', 'name')->hasCount(3)->contains('x');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $this->assertIsArray($a);
        $this->assertArrayHasKey('id', $a);
        $this->assertArrayHasKey('name', $a);
        $this->assertCount(3, $a);
        $this->assertContains('x', $a);
    }
}
