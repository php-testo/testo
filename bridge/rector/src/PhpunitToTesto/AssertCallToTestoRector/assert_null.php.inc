<?php

class SomeTest
{
    public function test()
    {
        $this->assertNull($value);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::null($value);
    }
}
