<?php

class SomeTest
{
    public function test()
    {
        throw new \Testo\Core\Exception\SkipTest();
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $this->markTestSkipped();
    }
}
