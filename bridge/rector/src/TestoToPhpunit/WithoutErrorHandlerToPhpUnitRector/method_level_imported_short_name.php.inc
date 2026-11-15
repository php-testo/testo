<?php

use Testo\ErrorHandler\WithoutErrorHandler;

class SomeTest
{
    #[\Testo\Test]
    #[WithoutErrorHandler]
    public function raw(): void
    {
    }
}
-----
<?php

use Testo\ErrorHandler\WithoutErrorHandler;

class SomeTest
{
    #[\Testo\Test]
    #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
    public function raw(): void
    {
    }
}
