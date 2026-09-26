<?php

abstract class LifecycleFunctionalTestCase extends \Tests\Bridge\Rector\Stub\VendorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }
}

final class LifecycleVendorChildTest extends \Tests\Bridge\Rector\Stub\VendorTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
    }
}

final class LifecycleVendorGrandchildTest extends LifecycleFunctionalTestCase
{
    public static function setUpBeforeClass(): void
    {
    }
}
-----
<?php

abstract class LifecycleFunctionalTestCase extends \Tests\Bridge\Rector\Stub\VendorTestCase
{
    #[\Testo\Lifecycle\BeforeTest]
    protected function setUp(): void
    {
        parent::setUp();
    }
}

final class LifecycleVendorChildTest extends \Tests\Bridge\Rector\Stub\VendorTestCase
{
    #[\Testo\Lifecycle\AfterTest]
    protected function tearDown(): void
    {
        parent::tearDown();
    }
}

final class LifecycleVendorGrandchildTest extends LifecycleFunctionalTestCase
{
    #[\Testo\Lifecycle\BeforeClass]
    public static function setUpBeforeClass(): void
    {
    }
}
