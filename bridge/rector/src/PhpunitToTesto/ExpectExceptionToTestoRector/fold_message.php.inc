<?php

class SomeTest
{
    public function test()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessage('boom');
    }
}
