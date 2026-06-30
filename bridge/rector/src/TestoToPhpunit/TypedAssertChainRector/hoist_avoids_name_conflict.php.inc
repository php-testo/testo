<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    public function test(): void
    {
        $value = 1;
        \Testo\Assert::array($this->all())->isList();
    }
}
-----
<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    public function test(): void
    {
        $value = 1;
        $value2 = $this->all();
        $this->assertIsArray($value2);
        $this->assertIsList($value2);
    }
}
