<?php

#[\Testo\Test]
final class HelperTest
{
    #[\Testo\Common\Attribute\AssertMethod]
    public function assertValid($value): void
    {
        \Testo\Assert::true($value);
    }

    public function itWorks(): void
    {
    }
}
-----
<?php

final class HelperTest extends \PHPUnit\Framework\TestCase
{
    #[\Testo\Common\Attribute\AssertMethod]
    public function assertValid($value): void
    {
        \Testo\Assert::true($value);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itWorks(): void
    {
    }
}
