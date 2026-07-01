<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::array($log->all())->isList();
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $value = $log->all();
        $this->assertIsArray($value);
        $this->assertIsList($value);
    }
}
