<?php

class AssertNamedTest extends \PHPUnit\Framework\TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function assertsSumWorks(): void
    {
        $this->assertSame(2, 1 + 1);
    }

    /**
     * @test
     */
    public function assertion_of_sum(): void
    {
        $this->assertSame(2, 1 + 1);
    }

    #[\Testo\Test]
    public function assertsConverted(): void
    {
        \Testo\Assert::same(2, 1 + 1);
    }

    protected function assertLater(): \Closure
    {
        return function (): void {
            $this->assertTrue(true);
        };
    }

    protected function assertViaArrow(): \Closure
    {
        return fn() => $this->assertTrue(true);
    }
}
