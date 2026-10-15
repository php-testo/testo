<?php

class ParentCallTest extends \PHPUnit\Framework\TestCase
{
    public function test(): void
    {
        parent::assertSame(1, 2);
    }

    public function assertEquals($expected, $actual, string $message = ''): void
    {
        parent::assertEquals($expected, $actual, $message);
    }
}
-----
<?php

class ParentCallTest extends \PHPUnit\Framework\TestCase
{
    public function test(): void
    {
        \Testo\Assert::same(2, 1);
    }

    public function assertEquals($expected, $actual, string $message = ''): void
    {
        \Testo\Assert::equals($actual, $expected, $message);
    }
}
