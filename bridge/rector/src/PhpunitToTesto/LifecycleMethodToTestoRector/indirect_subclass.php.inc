<?php

abstract class LifecycleBaseTestCase extends \PHPUnit\Framework\TestCase
{
}

final class LifecycleChildTest extends LifecycleBaseTestCase
{
    protected function tearDown(): void
    {
    }
}
-----
<?php

abstract class LifecycleBaseTestCase extends \PHPUnit\Framework\TestCase
{
}

final class LifecycleChildTest extends LifecycleBaseTestCase
{
    #[\Testo\Lifecycle\AfterTest]
    protected function tearDown(): void
    {
    }
}
