<?php

#[\Testo\Test]
#[\Testo\Retry(maxAttempts: 4)]
class SomeTest
{
    public function testA(): void
    {
    }

    public static function provider(): array
    {
        return [];
    }

    public function helper(): string
    {
        return 'x';
    }
}
-----
<?php

#[\Testo\Test]
class SomeTest
{
    #[\PHPUnit\Framework\Attributes\Retry(4)]
    public function testA(): void
    {
    }

    public static function provider(): array
    {
        return [];
    }

    public function helper(): string
    {
        return 'x';
    }
}
