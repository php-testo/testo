<?php

class FooTest
{
    #[\Testo\Data\DataProvider('provideCases')]
    public function testIt($value): void
    {
    }
}
-----
<?php

class FooTest
{
    #[\PHPUnit\Framework\Attributes\DataProvider('provideCases')]
    public function testIt($value): void
    {
    }
}
