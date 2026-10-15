<?php

class AssertNotTrueNotFalseTest extends \PHPUnit\Framework\TestCase
{
    public function test()
    {
        $this->assertNotTrue($flag);
        self::assertNotFalse($result, 'must not fail');
    }
}
-----
<?php

class AssertNotTrueNotFalseTest extends \PHPUnit\Framework\TestCase
{
    public function test()
    {
        \Testo\Assert::notSame($flag, true);
        \Testo\Assert::notSame($result, false, 'must not fail');
    }
}
