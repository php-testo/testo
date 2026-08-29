<?php

class SomeTest
{
    public function test()
    {
        $this->assertArrayHasKey('id', $row);
        $this->assertArrayNotHasKey('secret', $row, 'this message is dropped');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::array($row)->hasKeys('id');
        \Testo\Assert::array($row)->doesNotHaveKeys('secret');
    }
}
