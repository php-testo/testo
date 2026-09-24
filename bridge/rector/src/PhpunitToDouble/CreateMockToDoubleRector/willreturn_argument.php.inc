<?php

class SomeTest
{
    public function test()
    {
        $dep->method('args')->willReturnArgument(1);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->allows('args')->resolves(fn(...$args) => $args[1]);
    }
}
