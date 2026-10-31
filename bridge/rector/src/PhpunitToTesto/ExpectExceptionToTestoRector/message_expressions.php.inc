<?php

class MessageExpressions
{
    private const MESSAGE = 'needle';

    public function constant(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(self::MESSAGE);
    }

    public function stringVariable(string $message): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($message);
    }

    public function concatenation(string $message): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('prefix ' . $message);
    }

    public function formatted(string $message): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf('prefix %s', $message));
    }

    public function effectful(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($this->message());
    }

    private function message(): string
    {
        return '';
    }
}
-----
<?php

class MessageExpressions
{
    private const MESSAGE = 'needle';

    public function constant(): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessageContaining(self::MESSAGE);
    }

    public function stringVariable(string $message): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessage($message, exact: $message === '');
    }

    public function concatenation(string $message): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessageContaining('prefix ' . $message);
    }

    public function formatted(string $message): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessageContaining(sprintf('prefix %s', $message));
    }

    public function effectful(): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessage(...(static fn(string $message): array => [$message, 'exact' => $message === ''])($this->message()));
    }

    private function message(): string
    {
        return '';
    }
}
