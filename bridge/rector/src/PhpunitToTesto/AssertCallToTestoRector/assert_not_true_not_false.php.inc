<?php

class AssertNotTrueNotFalseTest
{
    public function test()
    {
        $this->assertNotTrue($flag);
        self::assertNotFalse($result, 'must not fail');
    }
}
-----
<?php

class AssertNotTrueNotFalseTest
{
    public function test()
    {
        \Testo\Assert::notSame($flag, true);
        \Testo\Assert::notSame($result, false, 'must not fail');
    }
}
