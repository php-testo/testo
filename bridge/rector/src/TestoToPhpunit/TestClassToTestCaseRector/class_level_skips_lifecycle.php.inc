<?php

#[\Testo\Test]
final class MyTest
{
    #[\Testo\Lifecycle\BeforeTest]
    public function prepare(): void
    {
    }
    public function setUp(): void
    {
    }
    public function itWorks(): void
    {
    }
}
-----
<?php

final class MyTest extends \PHPUnit\Framework\TestCase
{
    #[\Testo\Lifecycle\BeforeTest]
    public function prepare(): void
    {
    }
    public function setUp(): void
    {
    }
    #[\PHPUnit\Framework\Attributes\Test]
    public function itWorks(): void
    {
    }
}
