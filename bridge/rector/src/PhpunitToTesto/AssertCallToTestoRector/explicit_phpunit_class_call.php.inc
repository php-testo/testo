<?php

final class AssertionHelpers
{
    public static function check($value): void
    {
        \PHPUnit\Framework\Assert::assertSame(1, $value);
        \PHPUnit\Framework\TestCase::assertTrue($value);
        \PHPUnit\Framework\Assert::assertThat($value, self::anything());
    }
}
-----
<?php

final class AssertionHelpers
{
    public static function check($value): void
    {
        \Testo\Assert::same($value, 1);
        \Testo\Assert::true($value);
        \PHPUnit\Framework\Assert::assertThat($value, self::anything());
    }
}
