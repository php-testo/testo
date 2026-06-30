<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::true($flag);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $this->assertTrue($flag);
    }
}
