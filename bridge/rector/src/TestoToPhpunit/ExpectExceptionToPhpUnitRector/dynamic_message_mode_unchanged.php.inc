<?php

class DynamicMessageModeTest
{
    public function test(string $message, bool $exact): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessage($message, exact: false);
        \Testo\Expect::exception(\RuntimeException::class)->withMessage('message', exact: $exact);
        \Testo\Expect::exception(\RuntimeException::class)->withMessage(...$arguments);
    }
}
