<?php

declare(strict_types=1);

#[\Testo\Test]
final class StrictMessageCoercion
{
    public function invalidStringStillThrows(): void
    {
        $message = false;
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage($message);
    }
}
-----
<?php

declare(strict_types=1);

#[\Testo\Test]
final class StrictMessageCoercion
{
    public function invalidStringStillThrows(): void
    {
        $message = false;
        \Testo\Expect::exception(\TypeError::class)->withMessage(...(static fn(string $message): array => [$message, 'exact' => $message === ''])($message));
    }
}
