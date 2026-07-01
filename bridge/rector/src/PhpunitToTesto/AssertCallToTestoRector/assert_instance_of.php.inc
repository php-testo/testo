<?php

class SomeTest
{
    public function test()
    {
        $this->assertInstanceOf(Foo::class, $object);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::instanceOf($object, Foo::class);
    }
}
