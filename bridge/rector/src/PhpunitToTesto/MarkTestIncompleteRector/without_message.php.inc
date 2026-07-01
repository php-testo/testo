<?php

class SomeTest
{
    public function test()
    {
        $this->markTestIncomplete();
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        throw new \Testo\Core\Exception\SkipTest('Incomplete');
    }
}
