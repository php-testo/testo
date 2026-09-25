<?php

class FooTest extends \PHPUnit\Framework\TestCase
{
    public static function tearDownAfterClass(): void
    {
    }
}
-----
<?php

class FooTest extends \PHPUnit\Framework\TestCase
{
    #[\Testo\Lifecycle\AfterClass]
    public static function tearDownAfterClass(): void
    {
    }
}
