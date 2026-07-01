<?php

class SomeTest
{
    public function test()
    {
        static::assertTrue($flag);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::true($flag);
    }
}
