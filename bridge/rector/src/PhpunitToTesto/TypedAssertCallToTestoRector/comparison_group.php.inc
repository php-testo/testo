<?php

class SomeTest
{
    public function test()
    {
        $this->assertGreaterThan(0, $n);
        $this->assertGreaterThanOrEqual(1, $n);
        $this->assertLessThan(100, $n);
        $this->assertLessThanOrEqual(99, $n, 'must fit');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::numeric($n)->greaterThan(0);
        \Testo\Assert::numeric($n)->greaterThanOrEqual(1);
        \Testo\Assert::numeric($n)->lessThan(100);
        \Testo\Assert::numeric($n)->lessThanOrEqual(99, 'must fit');
    }
}
