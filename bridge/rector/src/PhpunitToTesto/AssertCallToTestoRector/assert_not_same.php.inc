<?php

class SomeTest
{
    public function test()
    {
        $this->assertNotSame(3, $result);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::notSame($result, 3);
    }
}
