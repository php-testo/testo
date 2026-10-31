<?php

class IndependentDynamicConstraints
{
    public function repeatedMessageAndPattern(): void
    {
        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage('old');
        $this->expectExceptionMessageMatches('~two~');
        $this->expectExceptionMessage($this->message());
    }

    public function dynamicMessageAndPattern(): void
    {
        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage($this->message());
        $this->expectExceptionMessageMatches('~two~');
    }
}
-----
<?php

class IndependentDynamicConstraints
{
    public function repeatedMessageAndPattern(): void
    {
        \Testo\Expect::exception(\Throwable::class)->withMessage('old', exact: false)->withMessageMatchingRegex('~two~')->withMessage(...(static fn(string $message): array => [$message, 'exact' => $message === ''])($this->message()));
    }

    public function dynamicMessageAndPattern(): void
    {
        \Testo\Expect::exception(\Throwable::class)->withMessage(...(static fn(string $message): array => [$message, 'exact' => $message === ''])($this->message()))->withMessageMatchingRegex('~two~');
    }
}
