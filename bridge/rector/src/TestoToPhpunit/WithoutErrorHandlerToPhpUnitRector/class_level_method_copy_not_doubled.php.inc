<?php

#[\Testo\ErrorHandler\WithoutErrorHandler]
class SomeTest
{
    #[\Testo\Test]
    #[\Testo\ErrorHandler\WithoutErrorHandler]
    public function a(): void
    {
    }
}
-----
<?php

class SomeTest
{
    #[\Testo\Test]
    #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
    public function a(): void
    {
    }
}
