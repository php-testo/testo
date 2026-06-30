<?php

class SomeTest
{
    public static function provide()
    {
        \Testo\Assert::same($result, 3);
    }
}
-----
<?php

class SomeTest
{
    public static function provide()
    {
        self::assertSame(3, $result);
    }
}
