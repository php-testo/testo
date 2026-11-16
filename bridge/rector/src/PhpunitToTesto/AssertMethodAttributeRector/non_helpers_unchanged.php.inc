<?php

class NonHelperTest extends \PHPUnit\Framework\TestCase
{
    public function testSomething(): void
    {
        $this->assertTrue(true);
    }

    protected function assertNothing(): bool
    {
        return $this->createStub(\stdClass::class) !== null;
    }

    #[\Testo\Common\Attribute\AssertMethod]
    protected function assertAlreadyMarked(): void
    {
        $this->assertTrue(true);
    }
}

abstract class AbstractHelper extends \PHPUnit\Framework\TestCase
{
    abstract protected function assertValid($value): void;
}

class CustomAssertions
{
    public function assertSame($expected, $actual): bool { return $expected === $actual; }

    public function assertMatches($value): void
    {
        $this->assertSame(1, $value);
    }
}
