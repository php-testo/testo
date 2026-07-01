<?php

final class FooTest
{
    #[\Testo\Test]
    public static function itWorks(): void
    {
    }

    public static function provideData(): array
    {
        return [];
    }
}
-----
<?php

final class FooTest extends \PHPUnit\Framework\TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function itWorks(): void
    {
    }

    public static function provideData(): array
    {
        return [];
    }
}
