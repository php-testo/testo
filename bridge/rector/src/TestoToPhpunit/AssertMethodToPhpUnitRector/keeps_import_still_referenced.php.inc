<?php

namespace App\Tests;

use Testo\Common\Attribute\AssertMethod;

final class Helpers
{
    public const MARKER = AssertMethod::class;

    #[AssertMethod]
    public static function assertValid($value): void
    {
        \Testo\Assert::true($value);
    }

    public static function checker(): \Closure
    {
        return #[AssertMethod] static fn($value) => \Testo\Assert::true($value);
    }
}
-----
<?php

namespace App\Tests;

use Testo\Common\Attribute\AssertMethod;

final class Helpers
{
    public const MARKER = AssertMethod::class;

    public static function assertValid($value): void
    {
        \Testo\Assert::true($value);
    }

    public static function checker(): \Closure
    {
        return #[AssertMethod] static fn($value) => \Testo\Assert::true($value);
    }
}
