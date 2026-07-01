<?php

class SomeTest
{
    public function test()
    {
        $this->assertSame(3, $result);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::same($result, 3);
    }
}
