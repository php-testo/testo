<?php

class HelperTest extends \PHPUnit\Framework\TestCase
{
    protected function assertValidUser($user): void
    {
        $this->assertNotEmpty($user);
    }

    private static function assertPositive(int $value): void
    {
        self::assertGreaterThan(0, $value);
    }

    protected function assertNever(): void
    {
        $this->fail('never');
    }

    protected function assertConverted(string $value): void
    {
        \Testo\Assert::string($value)->contains('x');
    }

    protected function assertViaFunction($value): void
    {
        \PHPUnit\Framework\assertTrue($value);
    }
}

trait ApiAssertions
{
    protected function assertJsonResponse($response): void
    {
        $this->assertSame(200, $response);
    }
}
-----
<?php

class HelperTest extends \PHPUnit\Framework\TestCase
{
    #[\Testo\Common\Attribute\AssertMethod]
    protected function assertValidUser($user): void
    {
        $this->assertNotEmpty($user);
    }

    #[\Testo\Common\Attribute\AssertMethod]
    private static function assertPositive(int $value): void
    {
        self::assertGreaterThan(0, $value);
    }

    #[\Testo\Common\Attribute\AssertMethod]
    protected function assertNever(): void
    {
        $this->fail('never');
    }

    #[\Testo\Common\Attribute\AssertMethod]
    protected function assertConverted(string $value): void
    {
        \Testo\Assert::string($value)->contains('x');
    }

    #[\Testo\Common\Attribute\AssertMethod]
    protected function assertViaFunction($value): void
    {
        \PHPUnit\Framework\assertTrue($value);
    }
}

trait ApiAssertions
{
    #[\Testo\Common\Attribute\AssertMethod]
    protected function assertJsonResponse($response): void
    {
        $this->assertSame(200, $response);
    }
}
