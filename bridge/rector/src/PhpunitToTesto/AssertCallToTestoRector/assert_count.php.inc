<?php

class SomeTest
{
    public function test()
    {
        $this->assertCount(3, $items);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::count($items, 3);
    }
}
