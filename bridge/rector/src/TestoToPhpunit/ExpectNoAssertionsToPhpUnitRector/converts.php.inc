<?php

class FooTest
{
    #[\Testo\Assert\ExpectNoAssertions]
    public function testNothing(): void
    {
    }
}
-----
<?php

class FooTest
{
    #[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
    public function testNothing(): void
    {
    }
}
