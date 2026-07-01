<?php

class SomeTest
{
    public function test()
    {
        $this->expectException(\RuntimeException::class);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Expect::exception(\RuntimeException::class);
    }
}
