<?php

#[\Testo\ErrorHandler\WithoutErrorHandler]
class SomeTest
{
    #[\Testo\Test]
    public function a(): void
    {
    }

    #[\Testo\Test]
    public function b(): void
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
    #[\Testo\Test]
    #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
    public function b(): void
    {
    }
}
