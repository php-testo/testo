<?php

final class ParentAssertionTest extends \PHPUnit\Framework\TestCase
{
    public function testViaParent(): void
    {
        parent::assertTrue(true);
        parent::assertThat(1, self::anything());
    }

    protected function assertViaParent($value): void
    {
        parent::assertSame(1, $value);
    }
}
-----
<?php

final class ParentAssertionTest
{
    #[\Testo\Test]
    public function testViaParent(): void
    {
        \PHPUnit\Framework\Assert::assertTrue(true);
        \PHPUnit\Framework\Assert::assertThat(1, self::anything());
    }

    protected function assertViaParent($value): void
    {
        \PHPUnit\Framework\Assert::assertSame(1, $value);
    }
}
