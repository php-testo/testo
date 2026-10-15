<?php

class SameNameSecondTest extends \PHPUnit\Framework\TestCase
{
    public function test(): void
    {
        $this->assertSame(1, 2);
    }
}
-----
<?php

class SameNameSecondTest extends \PHPUnit\Framework\TestCase
{
    public function test(): void
    {
        \Testo\Assert::same(2, 1);
    }
}
