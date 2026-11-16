<?php

final class Helpers
{
    #[\Deprecated]
    protected function assertValid($value): void
    {
        \Testo\Assert::true($value);
    }
}
