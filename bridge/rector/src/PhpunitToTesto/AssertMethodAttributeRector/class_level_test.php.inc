<?php

#[\Testo\Test]
final class ClassLevelTest
{
    public function assertsSum(): void
    {
        \Testo\Assert::same(2, 1 + 1);
    }

    private function assertPositive(int $value): void
    {
        \Testo\Assert::true($value > 0);
    }
}

trait PublicTraitHelpers
{
    public function assertValid($value): void
    {
        \Testo\Assert::true($value);
    }

    public static function assertChecked($value): bool
    {
        \Testo\Assert::true($value);

        return true;
    }
}
-----
<?php

#[\Testo\Test]
final class ClassLevelTest
{
    public function assertsSum(): void
    {
        \Testo\Assert::same(2, 1 + 1);
    }

    #[\Testo\Common\Attribute\AssertMethod]
    private function assertPositive(int $value): void
    {
        \Testo\Assert::true($value > 0);
    }
}

trait PublicTraitHelpers
{
    public function assertValid($value): void
    {
        \Testo\Assert::true($value);
    }

    #[\Testo\Common\Attribute\AssertMethod]
    public static function assertChecked($value): bool
    {
        \Testo\Assert::true($value);

        return true;
    }
}
