<?php

class FooTest
{
    /**
     * @dataProvider provideCases
     */
    public function testX(int $a): void
    {
    }
}
-----
<?php

class FooTest
{
    #[\Testo\Data\DataProvider('provideCases')]
    public function testX(int $a): void
    {
    }
}
