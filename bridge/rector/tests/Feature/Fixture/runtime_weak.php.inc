<?php

final class WeakStringRuntimeContracts extends \PHPUnit\Framework\TestCase
{
    private array $trace = [];

    public function testNamed(): void
    {
        $this->assertStringContainsString(needle: 'x', haystack: 'xyz');
        $needle = 'x';
        $haystack = 'xyz';
        $this->assertStringContainsString(haystack: $haystack, needle: $needle);
    }

    public function testNamedReversed(): void
    {
        $this->trace = [];
        $this->assertStringContainsString(haystack: $this->mark('haystack', 'xyz'), needle: $this->mark('needle', 'x'), message: $this->mark('message', 'named'));
        \Testo\Assert::same($this->trace, ['haystack', 'needle', 'message']);
    }

    public function testWeakScalar(): void
    {
        $this->assertStringContainsString('2', 123);
    }

    public function testPhpDocCannotReplaceNativeType(): void
    {
        $this->annotatedHaystack(123);
    }

    public function testWeakStringable(): void
    {
        $this->trace = [];
        $this->assertStringContainsString('x', new class(fn() => $this->mark('cast', 'xyz')) implements \Stringable {
            public function __construct(private \Closure $value) {}
            public function __toString(): string { return ($this->value)(); }
        });
        \Testo\Assert::same($this->trace, ['cast']);
    }

    public function testPositionalAndNamedMessage(): void
    {
        $this->assertStringContainsString('x', 'xyz', message: 'present');
        $this->assertStringStartsWith(message: 'context', prefix: 'x', string: 'xyz');
    }

    public function testFunctionNamedReversed(): void
    {
        \PHPUnit\Framework\assertStringContainsString(haystack: 'xyz', needle: 'x');
    }

    public function testSelfAndStatic(): void
    {
        self::assertStringContainsString('2', 123);
        static::assertStringContainsString(haystack: 'xyz', needle: 'x');
    }

    public function testWeakMessageScalar(): void
    {
        $this->assertStringContainsString('x', 'xyz', message: 123);
    }

    public function testPositionalEvaluationOrder(): void
    {
        $this->trace = [];
        $this->assertStringContainsString($this->mark('needle', 'x'), $this->mark('haystack', 'xyz'), $this->mark('message', 'present'));
        \Testo\Assert::same($this->trace, ['needle', 'haystack', 'message']);
    }

    public function testNecessaryFailure(): void
    {
        $this->assertStringContainsString('missing', 'xyz');
    }

    public function testReturnRemainsNull(): void
    {
        \Testo\Assert::same($this->assertStringContainsString(needle: 'x', haystack: 'xyz'), null);
    }

    public function testPositionalReturnRemainsNull(): void
    {
        \Testo\Assert::same($this->assertStringContainsString('x', 'xyz'), null);
        \Testo\Assert::same($this->assertionReturn(), null);
    }

    private function mark(string $label, string $value): string
    {
        $this->trace[] = $label;
        return $value;
    }

    /** @param string $haystack */
    private function annotatedHaystack($haystack): void
    {
        $this->assertStringContainsString('2', $haystack);
    }

    private function assertionReturn(): mixed
    {
        return $this->assertStringContainsString('x', 'xyz');
    }
}
