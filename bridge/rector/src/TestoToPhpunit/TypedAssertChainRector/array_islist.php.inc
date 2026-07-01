<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::array($items)->isList();
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $this->assertIsArray($items);
        $this->assertIsList($items);
    }
}
