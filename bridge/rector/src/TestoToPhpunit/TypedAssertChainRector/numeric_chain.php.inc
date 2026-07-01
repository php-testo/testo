<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::int($n)->greaterThan(0)->lessThanOrEqual(100);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $this->assertIsInt($n);
        $this->assertGreaterThan(0, $n);
        $this->assertLessThanOrEqual(100, $n);
    }
}
