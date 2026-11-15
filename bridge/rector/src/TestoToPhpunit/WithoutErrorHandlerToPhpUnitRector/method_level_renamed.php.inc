<?php

class SomeTest
{
    #[\Testo\Test]
    #[\Testo\ErrorHandler\WithoutErrorHandler]
    public function raw(): void
    {
    }
}
-----
<?php

class SomeTest
{
    #[\Testo\Test]
    #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
    public function raw(): void
    {
    }
}
