<?php

class SomeTest
{
    public function test()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionObject(new \RuntimeException('boom'));
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Expect::exception(\RuntimeException::class);
        $this->expectExceptionObject(new \RuntimeException('boom'));
    }
}
