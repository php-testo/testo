<?php

class SomeTest
{
    public function test()
    {
        \Testo\Expect::exception(\RuntimeException::class)->withCode(7);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(7);
    }
}
