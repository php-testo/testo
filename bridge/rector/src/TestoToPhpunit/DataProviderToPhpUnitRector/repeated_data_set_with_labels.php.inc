<?php

class FooTest
{
    #[\Testo\Data\DataSet([1, 2], 'pair')]
    #[\Testo\Data\DataSet(['a' => 1, 'b' => 2], 'named')]
    public function testIt($a, $b): void
    {
    }
}
-----
<?php

class FooTest
{
    #[\PHPUnit\Framework\Attributes\TestWith([1, 2], 'pair')]
    #[\PHPUnit\Framework\Attributes\TestWith(['a' => 1, 'b' => 2], 'named')]
    public function testIt($a, $b): void
    {
    }
}
