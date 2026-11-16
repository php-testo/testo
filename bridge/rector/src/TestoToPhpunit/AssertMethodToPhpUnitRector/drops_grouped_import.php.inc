<?php

namespace App\Tests;

use Testo\Common\Attribute\{AssertMethod};
use Testo\{Assert, Test};

#[Test]
final class Helpers
{
    #[AssertMethod]
    private function assertValid($value): void
    {
        Assert::true($value);
    }
}
-----
<?php

namespace App\Tests;

use Testo\{Assert, Test};

#[Test]
final class Helpers
{
    private function assertValid($value): void
    {
        Assert::true($value);
    }
}
