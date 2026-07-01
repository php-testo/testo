<?php

class SomeTest
{
    public function test()
    {
        throw new \Testo\Core\Exception\SkipTest('not applicable');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $this->markTestSkipped('not applicable');
    }
}
