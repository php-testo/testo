<?php

class FooTest extends \PHPUnit\Framework\TestCase
{
    #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
    public function testRaw(): void
    {
    }
}
-----
<?php

class FooTest extends \PHPUnit\Framework\TestCase
{
    #[\Testo\ErrorHandler\WithoutErrorHandler]
    public function testRaw(): void
    {
    }
}
