<?php

class FooTest
{
    #[\PHPUnit\Framework\Attributes\DataProvider('provideCases')]
    public function testX(): void
    {
    }
}
-----
<?php

class FooTest
{
    #[\Testo\Data\DataProvider('provideCases')]
    public function testX(): void
    {
    }
}
