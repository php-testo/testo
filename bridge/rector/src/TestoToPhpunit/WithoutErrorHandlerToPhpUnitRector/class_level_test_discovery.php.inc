<?php

#[\Testo\Test]
#[\Testo\ErrorHandler\WithoutErrorHandler]
class SomeTest
{
    public function a(): void
    {
    }

    public function value(): int
    {
        return 1;
    }

    public static function b(): void
    {
    }

    #[\Testo\Lifecycle\BeforeTest]
    public function prepare(): void
    {
    }

    private function helper(): void
    {
    }
}
-----
<?php

#[\Testo\Test]
class SomeTest
{
    #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
    public function a(): void
    {
    }

    public function value(): int
    {
        return 1;
    }

    public static function b(): void
    {
    }

    #[\Testo\Lifecycle\BeforeTest]
    public function prepare(): void
    {
    }

    private function helper(): void
    {
    }
}
