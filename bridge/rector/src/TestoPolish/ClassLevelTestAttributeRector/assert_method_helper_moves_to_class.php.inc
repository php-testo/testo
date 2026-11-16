<?php

final class HelperTest
{
    #[\Testo\Test]
    public function counts(): void
    {
    }

    #[\Testo\Common\Attribute\AssertMethod]
    public function assertValid($value): void
    {
        \Testo\Assert::true($value);
    }
}
-----
<?php

#[\Testo\Test]
final class HelperTest
{
    public function counts(): void
    {
    }

    #[\Testo\Common\Attribute\AssertMethod]
    public function assertValid($value): void
    {
        \Testo\Assert::true($value);
    }
}
