<?php

abstract class AnonymousProjectBaseTestCase extends \PHPUnit\Framework\TestCase
{
}

final class AnonymousOnProjectBaseFactory
{
    public function make(): object
    {
        return new class extends AnonymousProjectBaseTestCase {
            #[\Override]
            protected function setUp(): void
            {
                parent::setUp();
                parent::markTestSkipped('later');
            }

            public function testFoo(): void
            {
            }
        };
    }
}
-----
<?php

abstract class AnonymousProjectBaseTestCase
{
}

final class AnonymousOnProjectBaseFactory
{
    public function make(): object
    {
        return new class extends AnonymousProjectBaseTestCase {
            protected function setUp(): void
            {
                self::markTestSkipped('later');
            }

            #[\Testo\Test]
            public function testFoo(): void
            {
            }
        };
    }
}
