<?php

class SomeTest
{
    public function test()
    {
        $this->markTestSkipped('not ready');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        throw new \Testo\Core\Exception\SkipTest('not ready');
    }
}
