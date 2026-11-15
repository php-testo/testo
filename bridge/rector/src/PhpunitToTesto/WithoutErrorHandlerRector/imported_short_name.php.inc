<?php

use PHPUnit\Framework\Attributes\WithoutErrorHandler;

class FooTest extends \PHPUnit\Framework\TestCase
{
    #[WithoutErrorHandler]
    public function testRaw(): void
    {
    }
}
-----
<?php

use PHPUnit\Framework\Attributes\WithoutErrorHandler;

class FooTest extends \PHPUnit\Framework\TestCase
{
    #[\Testo\ErrorHandler\WithoutErrorHandler]
    public function testRaw(): void
    {
    }
}
