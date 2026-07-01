<?php

class SomeTest
{
    public function test()
    {
        $this->fail('nope');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::fail('nope');
    }
}
