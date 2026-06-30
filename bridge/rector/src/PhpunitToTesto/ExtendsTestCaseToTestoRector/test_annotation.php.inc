<?php

final class FooTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @test
     */
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
