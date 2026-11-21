<?php

#[\Testo\Requires([Env::class, 'hasDocker'], 'class requirement')]
class SomeTest
{
    #[\Testo\Test]
    public function testA(): void
    {
    }

    #[\Testo\Test]
    #[\Testo\Requires([Env::class, 'hasNetwork'])]
    #[\Testo\Requires([Env::class, 'hasDisk'])]
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
        if (!Env::hasDocker()) {
            $this->markTestSkipped('class requirement');
        }
    }

    #[\Testo\Test]
    public function testB(): void
    {
        if (!Env::hasDocker()) {
            $this->markTestSkipped('class requirement');
        }
        if (!Env::hasNetwork()) {
            $this->markTestSkipped();
        }
        if (!Env::hasDisk()) {
            $this->markTestSkipped();
        }
    }
}
