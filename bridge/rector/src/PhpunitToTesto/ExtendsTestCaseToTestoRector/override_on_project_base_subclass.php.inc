<?php

abstract class OverrideProjectBaseTestCase extends \PHPUnit\Framework\TestCase
{
    protected function boot(): void
    {
    }

    #[\Override]
    protected function tearDown(): void
    {
    }
}

final class OverrideProjectBaseChildTest extends OverrideProjectBaseTestCase
{
    #[\Override]
    protected function setUp(): void
    {
    }

    #[\Override]
    protected function tearDown(): void
    {
    }

    #[\Override]
    protected function boot(): void
    {
    }

    public function testFoo(): void
    {
    }
}
-----
<?php

abstract class OverrideProjectBaseTestCase
{
    protected function boot(): void
    {
    }

    protected function tearDown(): void
    {
    }
}

final class OverrideProjectBaseChildTest extends OverrideProjectBaseTestCase
{
    protected function setUp(): void
    {
    }

    #[\Override]
    protected function tearDown(): void
    {
    }

    #[\Override]
    protected function boot(): void
    {
    }

    #[\Testo\Test]
    public function testFoo(): void
    {
    }
}
