<?php

class SomeTest
{
    public function test()
    {
        self::markTestIncomplete('x');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        throw new \Testo\Core\Exception\SkipTest('Incomplete: x');
    }
}
