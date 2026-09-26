<?php

namespace App\Tests;

use Testo\Test;
use Testo\Assert as Check;

#[\Testo\Test]
final class ImportReuseTest
{
    public function works(): void
    {
        \Testo\Assert::same(1, 1);
        Check::true(true);
    }

    #[Test]
    public function keepsShortNames(): void
    {
    }
}
-----
<?php

namespace App\Tests;

use Testo\Test;
use Testo\Assert as Check;

#[Test]
final class ImportReuseTest
{
    public function works(): void
    {
        Check::same(1, 1);
        Check::true(true);
    }

    #[Test]
    public function keepsShortNames(): void
    {
    }
}
