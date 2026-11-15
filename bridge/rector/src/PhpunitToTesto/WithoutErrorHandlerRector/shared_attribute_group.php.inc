<?php

class FooTest extends \PHPUnit\Framework\TestCase
{
    #[\PHPUnit\Framework\Attributes\Test, \PHPUnit\Framework\Attributes\WithoutErrorHandler]
    public function raw(): void
    {
    }
}
-----
<?php

class FooTest extends \PHPUnit\Framework\TestCase
{
    #[\PHPUnit\Framework\Attributes\Test, \Testo\ErrorHandler\WithoutErrorHandler]
    public function raw(): void
    {
    }
}
