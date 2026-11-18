<?php

abstract class AnonymousSetBaseTestCase extends \PHPUnit\Framework\TestCase
{
}

final class AnonymousSetFactory
{
    public function make(): object
    {
        return new class extends AnonymousSetBaseTestCase {
            protected function setUp(): void
            {
                parent::setUp();
                parent::markTestSkipped('later');
            }
        };
    }
}
-----
<?php

abstract class AnonymousSetBaseTestCase
{
}

final class AnonymousSetFactory
{
    public function make(): object
    {
        return new class extends AnonymousSetBaseTestCase {
            #[\Testo\Lifecycle\BeforeTest]
            protected function setUp(): void
            {
                throw new \Testo\Core\Exception\SkipTest('later');
            }
        };
    }
}
