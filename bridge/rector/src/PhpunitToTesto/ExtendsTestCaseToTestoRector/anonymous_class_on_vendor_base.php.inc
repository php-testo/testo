<?php

final class AnonymousOnVendorBaseFactory
{
    public function make(): object
    {
        return new class extends \Tests\Bridge\Rector\Stub\VendorTestCase {
            protected function setUp(): void
            {
                parent::setUp();
            }

            public function testFoo(): void
            {
            }
        };
    }
}
-----
<?php

final class AnonymousOnVendorBaseFactory
{
    public function make(): object
    {
        return new class extends \Tests\Bridge\Rector\Stub\VendorTestCase {
            protected function setUp(): void
            {
                parent::setUp();
            }

            #[\Testo\Test]
            public function testFoo(): void
            {
            }
        };
    }
}
