<?php

class SomeTest extends BaseTest
{
    #[\Testo\Test]
    #[\Testo\Requires('self::hasDocker')]
    public function testSelf(): void
    {
    }

    #[\Testo\Test]
    #[\Testo\Requires('hasDocker')]
    public function testOwnStaticMethod(): void
    {
    }

    #[\Testo\Test]
    #[\Testo\Requires('isWindows')]
    public function testInstanceMethodIsNotACondition(): void
    {
    }

    #[\Testo\Test]
    #[\Testo\Requires('App\Env\isLinux')]
    public function testNamespacedFunction(): void
    {
    }

    #[\Testo\Test]
    #[\Testo\Requires(['self', 'hasDocker'])]
    public function testArrayOfStrings(): void
    {
    }

    #[\Testo\Test]
    #[\Testo\Requires(['App\Probe', 'check'])]
    public function testArrayWithClassString(): void
    {
    }

    private static function hasDocker(): bool
    {
        return false;
    }

    public function isWindows(): bool
    {
        return false;
    }
}
-----
<?php

class SomeTest extends BaseTest
{
    #[\Testo\Test]
    public function testSelf(): void
    {
        if (!self::hasDocker()) {
            $this->markTestSkipped();
        }
    }

    #[\Testo\Test]
    public function testOwnStaticMethod(): void
    {
        if (!self::hasDocker()) {
            $this->markTestSkipped();
        }
    }

    #[\Testo\Test]
    public function testInstanceMethodIsNotACondition(): void
    {
        if (!\isWindows()) {
            $this->markTestSkipped();
        }
    }

    #[\Testo\Test]
    public function testNamespacedFunction(): void
    {
        if (!\App\Env\isLinux()) {
            $this->markTestSkipped();
        }
    }

    #[\Testo\Test]
    public function testArrayOfStrings(): void
    {
        if (!self::hasDocker()) {
            $this->markTestSkipped();
        }
    }

    #[\Testo\Test]
    public function testArrayWithClassString(): void
    {
        if (!\App\Probe::check()) {
            $this->markTestSkipped();
        }
    }

    private static function hasDocker(): bool
    {
        return false;
    }

    public function isWindows(): bool
    {
        return false;
    }
}
