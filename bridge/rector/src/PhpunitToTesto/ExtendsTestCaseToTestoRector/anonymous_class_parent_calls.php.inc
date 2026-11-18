<?php

final class AnonymousFactory
{
    public function make(): object
    {
        return new class extends \PHPUnit\Framework\TestCase {
            protected function setUp(): void
            {
                parent::setUp();
                parent::markTestIncomplete('later');
            }
        };
    }
}
-----
<?php

final class AnonymousFactory
{
    public function make(): object
    {
        return new class {
            protected function setUp(): void
            {
                self::markTestIncomplete('later');
            }
        };
    }
}
