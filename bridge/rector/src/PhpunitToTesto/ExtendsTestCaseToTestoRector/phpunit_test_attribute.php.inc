<?php

final class FooTest extends \PHPUnit\Framework\TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function itWorks(): void
    {
    }
}
-----
<?php

final class FooTest
{
    #[\Testo\Test]
    public function itWorks(): void
    {
    }
}
