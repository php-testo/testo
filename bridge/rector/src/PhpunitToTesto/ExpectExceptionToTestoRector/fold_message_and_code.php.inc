<?php

class SomeTest
{
    public function test()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');
        $this->expectExceptionCode(7);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessage('boom')->withCode(7);
    }
}
