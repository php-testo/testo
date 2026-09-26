<?php

abstract class VendorFunctionalTestCase extends \Tests\Bridge\Rector\Stub\VendorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }
}

final class VendorGrandchildTest extends VendorFunctionalTestCase
{
    public function testThroughLocalBase(): void
    {
    }
}

final class VendorChildTest extends \Tests\Bridge\Rector\Stub\VendorTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testDirect(): void
    {
    }
}

#[\Testo\Skip('waits for the new API')]
final class VendorAlreadySkippedTest extends \Tests\Bridge\Rector\Stub\VendorTestCase
{
    public function testSkipped(): void
    {
    }
}
-----
<?php

#[\Testo\Skip('Extends Tests\Bridge\Rector\Stub\VendorTestCase, a PHPUnit base from vendor: rewrite the base for Testo, then remove this attribute')]
abstract class VendorFunctionalTestCase extends \Tests\Bridge\Rector\Stub\VendorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }
}

final class VendorGrandchildTest extends VendorFunctionalTestCase
{
    #[\Testo\Test]
    public function testThroughLocalBase(): void
    {
    }
}

#[\Testo\Skip('Extends Tests\Bridge\Rector\Stub\VendorTestCase, a PHPUnit base from vendor: rewrite the base for Testo, then remove this attribute')]
final class VendorChildTest extends \Tests\Bridge\Rector\Stub\VendorTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    #[\Testo\Test]
    public function testDirect(): void
    {
    }
}

#[\Testo\Skip('waits for the new API')]
final class VendorAlreadySkippedTest extends \Tests\Bridge\Rector\Stub\VendorTestCase
{
    #[\Testo\Test]
    public function testSkipped(): void
    {
    }
}
