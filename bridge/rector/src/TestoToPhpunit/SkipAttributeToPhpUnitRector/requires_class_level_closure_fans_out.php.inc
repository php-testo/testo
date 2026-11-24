<?php

#[\Testo\Requires(static function (): bool { return true; })]
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
    public function testA(): void
    {
        if (!(static function (): bool { return true; })()) {
            $this->markTestSkipped();
        }
    }

    #[\Testo\Test]
    public function testB(): void
    {
        if (!(static function (): bool { return true; })()) {
            $this->markTestSkipped();
        }
    }
}
