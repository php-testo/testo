<?php

class FooTest
{
    public static function tearDownAfterClass(): void
    {
    }
}
-----
<?php

class FooTest
{
    #[\Testo\Lifecycle\AfterClass]
    public static function tearDownAfterClass(): void
    {
    }
}
