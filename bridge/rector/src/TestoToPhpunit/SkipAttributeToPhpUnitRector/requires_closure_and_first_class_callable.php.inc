<?php

class SomeTest
{
    #[\Testo\Test]
    #[\Testo\Requires(gc_enabled(...), 'fcc')]
    public function testFirstClassCallable(): void
    {
    }

    #[\Testo\Test]
    #[\Testo\Requires(static function (): bool { return true; }, 'closure')]
    public function testClosure(): void
    {
    }
}
-----
<?php

class SomeTest
{
    #[\Testo\Test]
    public function testFirstClassCallable(): void
    {
        if (!gc_enabled()) {
            $this->markTestSkipped('fcc');
        }
    }

    #[\Testo\Test]
    public function testClosure(): void
    {
        if (!(static function (): bool { return true; })()) {
            $this->markTestSkipped('closure');
        }
    }
}
