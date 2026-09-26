<?php

namespace App\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImportPhpunitUsedTest
{
    #[\Testo\Test]
    public function works(): void
    {
        \Testo\Assert::same(Test::class, 'x');
    }

    /**
     * @see TestCase
     */
    public function documented(): void
    {
    }
}
-----
<?php

namespace App\Tests;

use Testo\Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImportPhpunitUsedTest
{
    #[\Testo\Test]
    public function works(): void
    {
        Assert::same(Test::class, 'x');
    }

    /**
     * @see TestCase
     */
    public function documented(): void
    {
    }
}
