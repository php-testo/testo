<?php

class SomeTest
{
    #[\Testo\Test]
    #[\Testo\Requires([self::class, 'hasSymlinks'], 'no symlinks')]
    public function testArray(): void
    {
        $this->doSomething();
    }

    #[\Testo\Test]
    #[\Testo\Requires('App\Env::hasGc', reason: 'needs gc')]
    public function testStaticString(): void
    {
    }

    #[\Testo\Test]
    #[\Testo\Requires(condition: 'gc_enabled')]
    public function testFunction(): void
    {
    }

    private static function hasSymlinks(): bool
    {
        return \PHP_OS_FAMILY !== 'Windows';
    }
}
-----
<?php

class SomeTest
{
    #[\Testo\Test]
    public function testArray(): void
    {
        if (!self::hasSymlinks()) {
            $this->markTestSkipped('no symlinks');
        }
        $this->doSomething();
    }

    #[\Testo\Test]
    public function testStaticString(): void
    {
        if (!\App\Env::hasGc()) {
            $this->markTestSkipped('needs gc');
        }
    }

    #[\Testo\Test]
    public function testFunction(): void
    {
        if (!\gc_enabled()) {
            $this->markTestSkipped();
        }
    }

    private static function hasSymlinks(): bool
    {
        return \PHP_OS_FAMILY !== 'Windows';
    }
}
