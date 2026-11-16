<?php

final class HookedTest
{
    #[\Testo\Test]
    #[\Testo\Common\Attribute\AssertMethod]
    public function assertValid(): void
    {
    }

    #[\Testo\Test, \Testo\Lifecycle\BeforeTest]
    public function prepare(): void
    {
    }

    #[\Testo\Test]
    public function itWorks(): void
    {
    }
}
-----
<?php

final class HookedTest extends \PHPUnit\Framework\TestCase
{
    #[\Testo\Common\Attribute\AssertMethod]
    public function assertValid(): void
    {
    }

    #[\Testo\Lifecycle\BeforeTest]
    public function prepare(): void
    {
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itWorks(): void
    {
    }
}
