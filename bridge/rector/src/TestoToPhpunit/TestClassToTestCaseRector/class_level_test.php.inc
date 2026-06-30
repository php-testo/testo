<?php

#[\Testo\Test]
final class FooTest
{
    public function itWorks(): void
    {
    }
    public function alsoWorks(): void
    {
    }
    public static function provideCases(): iterable
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
    #[\PHPUnit\Framework\Attributes\Test]
    public function alsoWorks(): void
    {
    }
    public static function provideCases(): iterable
    {
        return [];
    }
}
