<?php

abstract class ParentCallsBaseTestCase extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->boot();
    }

    protected function boot(): void
    {
    }
}

final class ParentCallsChildTest extends ParentCallsBaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testFoo(): void
    {
    }
}
-----
<?php

abstract class ParentCallsBaseTestCase
{
    protected function setUp(): void
    {
        $this->boot();
    }

    protected function boot(): void
    {
    }
}

final class ParentCallsChildTest extends ParentCallsBaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
    }

    #[\Testo\Test]
    public function testFoo(): void
    {
    }
}
