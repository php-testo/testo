<?php

namespace App\Tests;

use App\Support\Expect;

final class Test
{
}

final class ImportClashTest
{
    #[\Testo\Test]
    public function works(): void
    {
        \Testo\Expect::exception(\RuntimeException::class);
        Expect::that(Assert::class);
        \Testo\Assert::same(1, 1);
    }
}
-----
<?php

namespace App\Tests;

use App\Support\Expect;

final class Test
{
}

final class ImportClashTest
{
    #[\Testo\Test]
    public function works(): void
    {
        \Testo\Expect::exception(\RuntimeException::class);
        Expect::that(Assert::class);
        \Testo\Assert::same(1, 1);
    }
}
