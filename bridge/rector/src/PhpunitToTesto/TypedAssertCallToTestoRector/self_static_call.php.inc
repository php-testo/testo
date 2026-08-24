<?php

class SomeTest
{
    public function test()
    {
        self::assertGreaterThan(0, $n);
        static::assertArrayHasKey('id', $row);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::numeric($n)->greaterThan(0);
        \Testo\Assert::array($row)->hasKeys('id');
    }
}
