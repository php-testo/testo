<?php

class SomeTest
{
    #[\Testo\Test]
    #[\Testo\Assert\ExpectException(\RuntimeException::class)]
    public function boom(): void
    {
        trigger();
    }
}
-----
<?php

class SomeTest
{
    #[\Testo\Test]
    public function boom(): void
    {
        $this->expectException(\RuntimeException::class);
        trigger();
    }
}
