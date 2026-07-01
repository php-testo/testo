<?php

class SomeTest
{
    public function test()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/boom/');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Expect::exception(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/boom/');
    }
}
