<?php

class FooTest extends \PHPUnit\Framework\TestCase
{
    public static function setUpBeforeClass(): void
    {
    }
}
-----
<?php

class FooTest extends \PHPUnit\Framework\TestCase
{
    #[\Testo\Lifecycle\BeforeClass]
    public static function setUpBeforeClass(): void
    {
    }
}
