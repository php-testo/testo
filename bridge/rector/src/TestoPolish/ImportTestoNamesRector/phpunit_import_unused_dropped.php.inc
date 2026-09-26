<?php

namespace App\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use App\Support\Factory;

final class ImportPhpunitUnusedTest
{
    #[\Testo\Test]
    public function works(): void
    {
        \Testo\Assert::same(Factory::make(), 1);
    }
}
-----
<?php

namespace App\Tests;

use Testo\Test;
use Testo\Assert;
use App\Support\Factory;

final class ImportPhpunitUnusedTest
{
    #[Test]
    public function works(): void
    {
        Assert::same(Factory::make(), 1);
    }
}
