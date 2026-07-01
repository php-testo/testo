<?php

class SomeTest
{
    public function test()
    {
        $this->assertFalse($flag);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::false($flag);
    }
}
