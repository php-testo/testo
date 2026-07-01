<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::int($n)->between(1, 10);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $this->assertIsInt($n);
        $this->assertGreaterThanOrEqual(1, $n);
        $this->assertLessThanOrEqual(10, $n);
    }
}
