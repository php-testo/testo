<?php

class SomeTest
{
    public function test()
    {
        $this->markTestIncomplete('todo');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        throw new \Testo\Core\Exception\SkipTest('Incomplete: todo');
    }
}
