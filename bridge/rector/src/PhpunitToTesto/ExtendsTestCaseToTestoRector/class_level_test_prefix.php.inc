<?php

final class FooTest extends \PHPUnit\Framework\TestCase
{
    public function testItWorks(): void
    {
    }

    public function helper(): int
    {
        return 1;
    }
}
-----
<?php

final class FooTest
{
    #[\Testo\Test]
    public function testItWorks(): void
    {
    }

    public function helper(): int
    {
        return 1;
    }
}
