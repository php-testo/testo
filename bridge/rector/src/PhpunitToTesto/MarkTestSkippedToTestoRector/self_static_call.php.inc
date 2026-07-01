<?php

class SomeTest
{
    public function test()
    {
        self::markTestSkipped('x');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        throw new \Testo\Core\Exception\SkipTest('x');
    }
}
