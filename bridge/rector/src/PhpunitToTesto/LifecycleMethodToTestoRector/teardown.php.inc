<?php

class FooTest extends \PHPUnit\Framework\TestCase
{
    protected function tearDown(): void
    {
    }
}
-----
<?php

class FooTest extends \PHPUnit\Framework\TestCase
{
    #[\Testo\Lifecycle\AfterTest]
    protected function tearDown(): void
    {
    }
}
