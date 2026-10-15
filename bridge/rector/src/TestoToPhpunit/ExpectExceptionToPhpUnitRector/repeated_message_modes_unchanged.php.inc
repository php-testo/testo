<?php

#[\Testo\Test]
final class RepeatedMessageModes
{
    public function emptySubstringWins(): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessage('a')->withMessage('', exact: false);
        throw new \RuntimeException('zzz');
    }

    public function exactMessageWins(): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessage('', exact: false)->withMessage('a');
        throw new \RuntimeException('a');
    }

    public function emptyModeChanges(): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessage('')->withMessage('', exact: false);
        throw new \RuntimeException('zzz');
    }
}
