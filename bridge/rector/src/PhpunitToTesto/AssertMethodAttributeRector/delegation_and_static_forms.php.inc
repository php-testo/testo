<?php

class DelegatingTest extends \PHPUnit\Framework\TestCase
{
    protected function assertOuter($value): void
    {
        $this->assertInner($value);
    }

    protected function assertInner($value): void
    {
        $this->assertTrue($value);
    }

    protected function assertViaParent($value): void
    {
        parent::assertTrue($value);
    }

    protected function assertNotHelper($value): bool
    {
        return $this->assertCustom($value);
    }

    protected function assertCustom($value): bool
    {
        return (bool) $value;
    }
}

final class StaticHelpers
{
    public static function assertPositive(int $value): void
    {
        \PHPUnit\Framework\Assert::assertGreaterThan(0, $value);
    }
}
-----
<?php

class DelegatingTest extends \PHPUnit\Framework\TestCase
{
    #[\Testo\Common\Attribute\AssertMethod]
    protected function assertOuter($value): void
    {
        $this->assertInner($value);
    }

    #[\Testo\Common\Attribute\AssertMethod]
    protected function assertInner($value): void
    {
        $this->assertTrue($value);
    }

    #[\Testo\Common\Attribute\AssertMethod]
    protected function assertViaParent($value): void
    {
        parent::assertTrue($value);
    }

    protected function assertNotHelper($value): bool
    {
        return $this->assertCustom($value);
    }

    protected function assertCustom($value): bool
    {
        return (bool) $value;
    }
}

final class StaticHelpers
{
    #[\Testo\Common\Attribute\AssertMethod]
    public static function assertPositive(int $value): void
    {
        \PHPUnit\Framework\Assert::assertGreaterThan(0, $value);
    }
}
