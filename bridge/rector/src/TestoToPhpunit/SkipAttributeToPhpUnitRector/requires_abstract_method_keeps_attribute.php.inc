<?php

abstract class SomeTest
{
    #[\Testo\Test]
    #[\Testo\Requires([Env::class, 'hasDocker'])]
    abstract public function testAbstract(): void;

    #[\Testo\Test]
    #[\Testo\Requires([Env::class, 'hasNetwork'])]
    public function testConcrete(): void
    {
    }
}
-----
<?php

abstract class SomeTest
{
    #[\Testo\Test]
    #[\Testo\Requires([Env::class, 'hasDocker'])]
    abstract public function testAbstract(): void;

    #[\Testo\Test]
    public function testConcrete(): void
    {
        if (!Env::hasNetwork()) {
            $this->markTestSkipped();
        }
    }
}
