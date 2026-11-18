<?php

final class StaticParentCallTest extends \PHPUnit\Framework\TestCase
{
    public static function bootOnce(): void
    {
        parent::bootKernel();
    }
}
-----
<?php

final class StaticParentCallTest
{
    public static function bootOnce(): void
    {
        self::bootKernel();
    }
}
