<?php

abstract class LifecycleAnonymousBaseTestCase extends \PHPUnit\Framework\TestCase
{
}

final class LifecycleAnonymousFactory
{
    public function make(): object
    {
        return new class extends LifecycleAnonymousBaseTestCase {
            protected function setUp(): void
            {
            }
        };
    }
}
-----
<?php

abstract class LifecycleAnonymousBaseTestCase extends \PHPUnit\Framework\TestCase
{
}

final class LifecycleAnonymousFactory
{
    public function make(): object
    {
        return new class extends LifecycleAnonymousBaseTestCase {
            #[\Testo\Lifecycle\BeforeTest]
            protected function setUp(): void
            {
            }
        };
    }
}
