<?php

use PHPUnit\Framework\TestCase;

trait HelperAssertions
{
    abstract public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void;

    protected function assertValid($value): void
    {
        $this->assertSame(1, $value);
        self::assertTrue($value);
    }
}

final class DirectTest extends TestCase
{
    use HelperAssertions;

    public function testSame(): void
    {
        $this->assertSame(1, 2);
        self::assertIsString('x');
    }

    protected function assertViaParent($value): void
    {
        parent::assertTrue($value);
        parent::assertThat($value, self::anything());
    }
}
-----
<?php

use PHPUnit\Framework\TestCase;

trait HelperAssertions
{
    #[\Testo\Common\Attribute\AssertMethod]
    protected function assertValid($value): void
    {
        \Testo\Assert::same($value, 1);
        \Testo\Assert::true($value);
    }
}

final class DirectTest
{
    use HelperAssertions;

    #[\Testo\Test]
    public function testSame(): void
    {
        \Testo\Assert::same(2, 1);
        \Testo\Assert::string('x');
    }

    #[\Testo\Common\Attribute\AssertMethod]
    protected function assertViaParent($value): void
    {
        \Testo\Assert::true($value);
        \PHPUnit\Framework\Assert::assertThat($value, self::anything());
    }
}
