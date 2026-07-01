<?php

class SomeTest
{
    public function test()
    {
        self::expectException(\RuntimeException::class);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Expect::exception(\RuntimeException::class);
    }
}
