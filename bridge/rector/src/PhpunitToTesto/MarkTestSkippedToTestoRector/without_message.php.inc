<?php

class SomeTest
{
    public function test()
    {
        $this->markTestSkipped();
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        throw new \Testo\Core\Exception\SkipTest();
    }
}
