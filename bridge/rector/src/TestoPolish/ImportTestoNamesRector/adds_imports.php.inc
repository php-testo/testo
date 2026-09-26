<?php

declare(strict_types=1);

namespace App\Tests;

final class ImportAddsTest
{
    #[\Testo\Test]
    #[\Testo\Data\DataProvider('cases')]
    public function works(int $value): void
    {
        \Testo\Assert::same(1, $value);

        \Testo\Expect::exception(\RuntimeException::class);
        throw new \RuntimeException('x');
    }

    public function skip(): void
    {
        throw new \Testo\Core\Exception\SkipTest('later');
    }

    public static function cases(): iterable
    {
        yield [1];
    }
}
-----
<?php

declare(strict_types=1);

namespace App\Tests;

use Testo\Test;
use Testo\Data\DataProvider;
use Testo\Assert;
use Testo\Expect;
use Testo\Core\Exception\SkipTest;

final class ImportAddsTest
{
    #[Test]
    #[DataProvider('cases')]
    public function works(int $value): void
    {
        Assert::same(1, $value);

        Expect::exception(\RuntimeException::class);
        throw new \RuntimeException('x');
    }

    public function skip(): void
    {
        throw new SkipTest('later');
    }

    public static function cases(): iterable
    {
        yield [1];
    }
}
