<?php

class FooTest
{
    public static function setUpBeforeClass(): void
    {
    }
}
-----
<?php

class FooTest
{
    #[\Testo\Lifecycle\BeforeClass]
    public static function setUpBeforeClass(): void
    {
    }
}
