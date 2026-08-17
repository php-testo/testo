<?php

// Testo's markFlaky flag has no PHPUnit equivalent and is dropped.
class SomeTest
{
    #[\Testo\Repeat(times: 2, markFlaky: false)]
    public function test(): void
    {
    }
}
-----
<?php

// Testo's markFlaky flag has no PHPUnit equivalent and is dropped.
class SomeTest
{
    #[\PHPUnit\Framework\Attributes\Repeat(2)]
    public function test(): void
    {
    }
}
