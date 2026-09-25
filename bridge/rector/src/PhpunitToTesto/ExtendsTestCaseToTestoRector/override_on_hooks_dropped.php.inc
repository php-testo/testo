<?php

final class FooTest extends \PHPUnit\Framework\TestCase
{
    #[\Override]
    #[\Testo\Lifecycle\BeforeTest]
    protected function setUp(): void
    {
    }

    #[\Override, \Testo\Lifecycle\AfterTest]
    protected function tearDown(): void
    {
    }

    #[\Override]
    public static function setUpBeforeClass(): void
    {
    }

    public function testFoo(): void
    {
    }
}
-----
<?php

final class FooTest
{
    #[\Testo\Lifecycle\BeforeTest]
    protected function setUp(): void
    {
    }

    #[\Testo\Lifecycle\AfterTest]
    protected function tearDown(): void
    {
    }

    public static function setUpBeforeClass(): void
    {
    }

    #[\Testo\Test]
    public function testFoo(): void
    {
    }
}
