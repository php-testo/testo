<?php

#[\Testo\Test, \Testo\ErrorHandler\WithoutErrorHandler]
class SomeTest
{
    public function a(): void
    {
    }
}
-----
<?php

#[\Testo\Test]
class SomeTest
{
    #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
    public function a(): void
    {
    }
}
