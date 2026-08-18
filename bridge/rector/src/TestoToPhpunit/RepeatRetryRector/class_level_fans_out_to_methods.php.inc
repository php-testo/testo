<?php

#[\Testo\Repeat(times: 3)]
class SomeTest
{
    #[\Testo\Test]
    public function testA(): void
    {
    }

    #[\Testo\Test]
    public function testB(): void
    {
    }
}
-----
<?php

class SomeTest
{
    #[\Testo\Test]
    #[\PHPUnit\Framework\Attributes\Repeat(3)]
    public function testA(): void
    {
    }
    #[\Testo\Test]
    #[\PHPUnit\Framework\Attributes\Repeat(3)]
    public function testB(): void
    {
    }
}
