<?php

#[\Testo\Requires([Env::class, 'hasDocker'])]
class SomeTest
{
    #[\Testo\Test]
    #[\Testo\Requires(cond: 'gc_enabled')]
    public function testMisspelled(): void
    {
    }

    #[\Testo\Test]
    public function testOther(): void
    {
    }
}
