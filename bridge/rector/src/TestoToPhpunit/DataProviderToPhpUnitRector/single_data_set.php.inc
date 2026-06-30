<?php

class FooTest
{
    #[\Testo\Data\DataSet([1, 2])]
    public function testIt($a, $b): void
    {
    }
}
-----
<?php

class FooTest
{
    #[\PHPUnit\Framework\Attributes\TestWith([1, 2])]
    public function testIt($a, $b): void
    {
    }
}
