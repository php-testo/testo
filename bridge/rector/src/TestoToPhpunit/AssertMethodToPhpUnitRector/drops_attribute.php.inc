<?php

trait ApiAssertions
{
    #[\Testo\Common\Attribute\AssertMethod]
    protected function assertJsonResponse($response): void
    {
        \Testo\Assert::same($response, 200);
    }

    #[\Testo\Common\Attribute\AssertMethod, \Deprecated]
    protected function assertLegacy($value): void
    {
        \Testo\Assert::true($value);
    }

    #[\Deprecated]
    #[\Testo\Common\Attribute\AssertMethod]
    protected function assertOld($value): void
    {
        \Testo\Assert::true($value);
    }
}

#[\Testo\Common\Attribute\AssertMethod]
function assertPositive(int $value): void
{
    \Testo\Assert::true($value > 0);
}
-----
<?php

trait ApiAssertions
{
    protected function assertJsonResponse($response): void
    {
        \Testo\Assert::same($response, 200);
    }

    #[\Deprecated]
    protected function assertLegacy($value): void
    {
        \Testo\Assert::true($value);
    }

    #[\Deprecated]
    protected function assertOld($value): void
    {
        \Testo\Assert::true($value);
    }
}

function assertPositive(int $value): void
{
    \Testo\Assert::true($value > 0);
}
