<?php

#[\Testo\Requires([Env::class, 'hasDocker'])]
class SomeTest
{
    #[\Testo\Test]
    #[\Testo\Skip('parked')]
    #[\Testo\Requires([Env::class, 'hasNetwork'])]
    public function testA(): void
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
        $this->markTestSkipped('parked');
    }
}
