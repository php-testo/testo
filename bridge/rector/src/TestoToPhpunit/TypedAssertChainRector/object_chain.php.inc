<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::object($o)->instanceOf(\Foo::class)->hasProperty('id');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $this->assertIsObject($o);
        $this->assertInstanceOf(\Foo::class, $o);
        $this->assertObjectHasProperty('id', $o);
    }
}
